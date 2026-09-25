<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Booking;
use App\Models\Court;
use App\Models\News;
use App\Models\Promotion;
use App\Models\Review;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;

class HomeController extends Controller
{
    /**
     * UC11 - Display home page
     */
    public function index()
    {
        // Get active banners
        $banners = Banner::where('status', 'ACTIVE')
            ->where(fn ($query) => $query->whereNull('start_at')->orWhere('start_at', '<=', now()))
            ->where(function ($query) {
                $query->whereNull('end_at')
                    ->orWhere('end_at', '>=', now());
            })
            ->orderBy('sort_order')
            ->get();

        // Display the homepage court selection in natural name order.
        $featuredPeriodDays = config('booking.featured_period_days', 30);
        $courtRelations = ['images', 'courtType', 'prices', 'amenities'];
        $bookingCount = fn ($query) => $query->whereHas('booking', fn ($booking) => $booking
            ->whereIn('status', ['CONFIRMED', 'COMPLETED']));

        $homepageCourtIds = Court::where('status', 'ACTIVE')->get(['id', 'name'])
            ->sort(fn ($a, $b) => strnatcasecmp($a->name, $b->name) ?: ($a->id <=> $b->id))
            ->take(6)->pluck('id');
        $featuredCourts = Court::whereIn('id', $homepageCourtIds)
            ->with($courtRelations)
            ->withCount(['bookingDetails as booking_count' => fn ($query) => $bookingCount($query)
                ->whereHas('booking', fn ($booking) => $booking->where('created_at', '>=', now()->subDays($featuredPeriodDays)))])
            ->withCount(['reviews as approved_reviews_count' => fn ($query) => $query->where('status', 'APPROVED')])
            ->withAvg(['reviews as approved_rating' => fn ($query) => $query->where('status', 'APPROVED')], 'rating')
            ->get()
            ->sort(fn ($a, $b) => strnatcasecmp($a->name, $b->name) ?: ($a->id <=> $b->id))
            ->values();

        // Get most booked courts
        $mostBookedCourts = Court::where('status', 'ACTIVE')
            ->with($courtRelations)
            ->withCount(['bookingDetails as booking_count' => $bookingCount])
            ->withCount(['reviews as approved_reviews_count' => fn ($query) => $query->where('status', 'APPROVED')])
            ->withAvg(['reviews as approved_rating' => fn ($query) => $query->where('status', 'APPROVED')], 'rating')
            ->orderByDesc('booking_count')
            ->orderByDesc('courts.created_at')
            ->limit(8)
            ->get();

        // Get active promotions
        $promotions = Promotion::where('status', 'ACTIVE')
            ->where(fn ($query) => $query->whereNull('start_at')->orWhere('start_at', '<=', now()))
            ->where(function ($query) {
                $query->whereNull('end_at')
                    ->orWhere('end_at', '>=', now());
            })
            ->limit(5)
            ->get();

        // Get latest news
        $news = News::where('status', 'PUBLISHED')
            ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->orderByDesc('published_at')
            ->limit(6)
            ->get();

        $reviews = Review::where('status', 'APPROVED')
            ->with(['user:id,name', 'court:id,name'])
            ->latest()
            ->limit(6)
            ->get();

        $statistics = [
            'bookings' => Booking::whereIn('status', ['CONFIRMED', 'COMPLETED'])->count(),
            'courts' => Court::where('status', 'ACTIVE')->count(),
            'customers' => User::count(),
            'rating' => round((float) Review::where('status', 'APPROVED')->avg('rating'), 1),
        ];

        $timeSlots = TimeSlot::where('status', 'ACTIVE')->orderBy('start_time')->get(['id', 'name', 'start_time']);
        $bannerPath = $banners->first()?->image;
        $heroImage = $bannerPath
            ? (\Illuminate\Support\Str::startsWith($bannerPath, ['https://', 'http://', '/']) ? $bannerPath : asset('storage/'.$bannerPath))
            : asset('images/banner.png').'?v='.filemtime(public_path('images/banner.png'));

        // Homepage preview only; booking still revalidates availability and price.
        $previewSlots = $timeSlots->filter(fn ($slot) => Carbon::parse(today()->toDateString().' '.$slot->start_time)->isFuture())->take(4);
        $liveCourts = $featuredCourts->take(2)->map(function ($court) use ($previewSlots) {
            return ['court' => $court, 'slots' => $previewSlots->map(function ($slot) use ($court) {
                $status = app(\App\Services\CourtAvailabilityService::class)->checkAvailability($court->id, Carbon::today(), $slot->id);
                $price = app(\App\Services\BookingService::class)->getCurrentPrice($court->id, $slot->id, Carbon::today());
                return ['slot' => $slot, 'status' => $price > 0 ? $status : 'MAINTENANCE'];
            })];
        });

        return view('home', [
            'venueAddress' => trim((string) \App\Models\SystemSetting::valueFor('address', '')),
            'venueMapQuery' => trim((string) \App\Models\SystemSetting::valueFor('map_query', \App\Models\SystemSetting::valueFor('address', ''))),
            'banners' => $banners,
            'featured_courts' => $featuredCourts,
            'most_booked_courts' => $mostBookedCourts,
            'promotions' => $promotions,
            'news' => $news,
            'reviews' => $reviews,
            'statistics' => $statistics,
            'timeSlots' => $timeSlots,
            'heroImage' => $heroImage,
            'liveCourts' => $liveCourts,
            'homeServices' => \App\Models\ServiceItem::where('is_active', true)->orderBy('name')->limit(8)->get(),
        ]);
    }
}
