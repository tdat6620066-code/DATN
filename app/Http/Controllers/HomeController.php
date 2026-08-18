<?php

namespace App\Http\Controllers;

use App\Models\{Banner, Booking, Court, News, Promotion, Review, TimeSlot, User};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    /**
     * UC11 - Display home page
     */
    public function index(Request $request)
    {
        if ($request->user()?->role === 'ADMIN') {
            return redirect()->route('admin.dashboard');
        }

        if ($request->user()?->role === 'EMPLOYEE') {
            return redirect()->route('employee.dashboard');
        }

        // Get active banners
        $banners = Banner::where('status', 'ACTIVE')
            ->where('start_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('end_at')
                    ->orWhere('end_at', '>=', now());
            })
            ->orderBy('sort_order')
            ->get();

        // Get featured courts (by booking count in last 30 days)
        $featuredPeriodDays = config('booking.featured_period_days', 30);
        $courtRelations = ['images', 'courtType', 'prices', 'amenities'];
        $bookingCount = fn ($query) => $query->whereHas('booking', fn ($booking) => $booking
            ->whereIn('status', ['CONFIRMED', 'COMPLETED']));

        $featuredCourts = Court::where('status', 'ACTIVE')
            ->with($courtRelations)
            ->withCount(['bookingDetails as booking_count' => fn ($query) => $bookingCount($query)
                ->whereHas('booking', fn ($booking) => $booking->where('created_at', '>=', now()->subDays($featuredPeriodDays)))])
            ->withCount(['reviews as approved_reviews_count' => fn ($query) => $query->where('status', 'APPROVED')])
            ->withAvg(['reviews as approved_rating' => fn ($query) => $query->where('status', 'APPROVED')], 'rating')
            ->orderByDesc('booking_count')
            ->limit(6)
            ->get();

        // Get most booked courts
        $mostBookedCourts = Court::where('status', 'ACTIVE')
            ->with($courtRelations)
            ->withCount(['bookingDetails as booking_count' => $bookingCount])
            ->withCount(['reviews as approved_reviews_count' => fn ($query) => $query->where('status', 'APPROVED')])
            ->withAvg(['reviews as approved_rating' => fn ($query) => $query->where('status', 'APPROVED')], 'rating')
            ->orderByDesc('booking_count')
            ->limit(8)
            ->get();

        // Get active promotions
        $promotions = Promotion::where('status', 'ACTIVE')
            ->where('start_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('end_at')
                    ->orWhere('end_at', '>=', now());
            })
            ->limit(5)
            ->get();

        // Get latest news
        $news = News::where('status', 'PUBLISHED')
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
        $heroImage = $banners->first()?->image ?? $featuredCourts->first()?->images->first()?->image;

        return view('home', [
            'banners' => $banners,
            'featured_courts' => $featuredCourts,
            'most_booked_courts' => $mostBookedCourts,
            'promotions' => $promotions,
            'news' => $news,
            'reviews' => $reviews,
            'statistics' => $statistics,
            'timeSlots' => $timeSlots,
            'heroImage' => $heroImage,
        ]);
    }
}
