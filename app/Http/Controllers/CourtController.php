<?php

namespace App\Http\Controllers;

use App\Http\Requests\{CheckAvailabilityRequest, FilterCourtRequest, SearchCourtRequest};
use App\Models\{Court, CourtPrice, CourtType, Review, TimeSlot};
use App\Services\CourtAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourtController extends Controller
{
    private CourtAvailabilityService $availabilityService;

    public function __construct(CourtAvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }

    /**
     * UC12 & UC14 - Display list of courts with search and filter
     */
    public function index(SearchCourtRequest $searchRequest, FilterCourtRequest $filterRequest)
    {
        $query = Court::where('status', 'ACTIVE')
            ->with(['courtType', 'images', 'prices', 'amenities'])
            ->withCount([
                'reviews as approved_reviews_count' => fn ($reviewQuery) => $reviewQuery->where('status', 'APPROVED'),
            ])
            ->withAvg([
                'reviews as approved_rating' => fn ($reviewQuery) => $reviewQuery->where('status', 'APPROVED'),
            ], 'rating');

        // UC12 - Search by keyword (name, code, court_type)
        if ($searchRequest->keyword) {
            $keyword = $searchRequest->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'LIKE', "%{$keyword}%")
                    ->orWhere('code', 'LIKE', "%{$keyword}%")
                    ->orWhereHas('courtType', function ($subQ) use ($keyword) {
                        $subQ->where('name', 'LIKE', "%{$keyword}%");
                    });
            });
        }

        // UC13 - Filter by various criteria
        if ($filterRequest->court_type_id) {
            $query->where('court_type_id', $filterRequest->court_type_id);
        }

        if ($filterRequest->amenity_ids && count($filterRequest->amenity_ids) > 0) {
            $query->whereHas('amenities', function ($q) use ($filterRequest) {
                $q->whereIn('amenities.id', $filterRequest->amenity_ids);
            }, '=', count($filterRequest->amenity_ids));
        }

        // Filter by selected time/date or price range. Price-only searches use
        // every active price; a selected date/time narrows that down further.
        if (($filterRequest->booking_date && $filterRequest->time_slot_id)
            || $filterRequest->price_min || $filterRequest->price_max) {
            $query->whereHas('prices', function ($q) use ($filterRequest) {
                $q->where('status', 'ACTIVE');

                if ($filterRequest->time_slot_id) {
                    $q->where('time_slot_id', $filterRequest->time_slot_id);
                }

                if ($filterRequest->booking_date) {
                    $q->where('effective_from', '<=', $filterRequest->booking_date)
                        ->where(function ($subQ) use ($filterRequest) {
                            $subQ->whereNull('effective_to')
                                ->orWhere('effective_to', '>=', $filterRequest->booking_date);
                        });
                }

                if ($filterRequest->price_min) {
                    $q->where('price', '>=', $filterRequest->price_min);
                }

                if ($filterRequest->price_max) {
                    $q->where('price', '<=', $filterRequest->price_max);
                }
            });
        }

        // UC13 - Sort
        $sortBy = $filterRequest->sort_by ?? 'name_asc';
        switch ($sortBy) {
            case 'price_asc':
                $query->orderBy(
                    CourtPrice::selectRaw('MIN(price)')
                        ->whereColumn('court_id', 'courts.id')
                        ->where('status', 'ACTIVE'),
                    'asc'
                );
                break;
            case 'price_desc':
                $query->orderBy(
                    CourtPrice::selectRaw('MIN(price)')
                        ->whereColumn('court_id', 'courts.id')
                        ->where('status', 'ACTIVE'),
                    'desc'
                );
                break;
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            case 'most_booked':
                $query->leftJoinSub(
                    DB::table('booking_details')
                        ->join('bookings', 'booking_details.booking_id', '=', 'bookings.id')
                        ->select('court_id')
                        ->selectRaw('COUNT(*) as booking_count')
                        ->whereIn('bookings.status', ['CONFIRMED', 'COMPLETED'])
                        ->groupBy('court_id'),
                    'bookings_stats',
                    'courts.id',
                    '=',
                    'bookings_stats.court_id'
                )->orderByDesc('booking_count');
                break;
        }

        $courts = $query->paginate(12)->withQueryString();

        $courtPreviewData = $courts->getCollection()->map(function (Court $court) {
            return [
                'id' => $court->id,
                'name' => $court->name,
                'address' => $court->address,
                'phone' => $court->phone,
                'opening' => $court->opening_time ? Carbon::parse($court->opening_time)->format('H:i') : null,
                'closing' => $court->closing_time ? Carbon::parse($court->closing_time)->format('H:i') : null,
                'description' => $court->description,
                'price' => $court->prices->min('price') ?? 0,
                'images' => $court->images->map(fn ($image) => $image->url)->values(),
                'amenities' => $court->amenities->pluck('name')->values(),
                'type' => $court->courtType?->name,
                'rating' => $court->approved_rating ? number_format($court->approved_rating, 1) : null,
                'reviews_count' => $court->approved_reviews_count ?? 0,
            ];
        })->keyBy('id');
        
        // Get court types for filter
        $courtTypes = CourtType::where('status', 'ACTIVE')->get();

        return view('courts.index', [
            'courts' => $courts,
            'courtTypes' => $courtTypes,
            'keyword' => $searchRequest->keyword ?? '',
            'courtPreviewData' => $courtPreviewData,
        ]);
    }

    /**
     * UC15 - Display court details
     */
    public function show(Request $request, Court $court)
    {
        // Only show active courts
        if ($court->status !== 'ACTIVE') {
            abort(404, 'Sân không tồn tại hoặc hiện không hoạt động.');
        }

        $court->load([
            'courtType',
            'images',
            'amenities',
            'prices' => function ($q) {
                $q->where('status', 'ACTIVE')
                    ->where('effective_from', '<=', now()->toDateString())
                    ->where(function ($subQ) {
                        $subQ->whereNull('effective_to')
                            ->orWhere('effective_to', '>=', now()->toDateString());
                    });
            }
        ]);

        // Get approved reviews with pagination
        $reviews = Review::where('court_id', $court->id)
            ->where('status', 'APPROVED')
            ->with('user', 'images')
            ->orderByDesc('created_at')
            ->paginate(10);

        // Calculate rating stats
        $ratingStats = Review::where('court_id', $court->id)
            ->where('status', 'APPROVED')
            ->selectRaw('
                COUNT(*) as total,
                AVG(rating) as average,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as count_5,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as count_4,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as count_3,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as count_2,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as count_1
            ')
            ->first();

        // Get active prices with time slots
        $activePrices = CourtPrice::where('court_id', $court->id)
            ->where('status', 'ACTIVE')
            ->where('effective_from', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now()->toDateString());
            })
            ->with('timeSlot')
            ->get();

        $selectedDate = $request->filled('booking_date')
            ? Carbon::parse($request->booking_date)->startOfDay()
            : Carbon::today();

        if ($selectedDate->lt(Carbon::today()) || $selectedDate->gt(Carbon::today()->addDays(config('booking.max_days', 30)))) {
            $selectedDate = Carbon::today();
        }

        $timeSlots = TimeSlot::where('status', 'ACTIVE')->orderBy('start_time')->get();
        $availability = [];
        foreach ($timeSlots as $slot) {
            $price = $court->prices()
                ->where('time_slot_id', $slot->id)
                ->where('status', 'ACTIVE')
                ->where('effective_from', '<=', $selectedDate->toDateString())
                ->where(function ($query) use ($selectedDate) {
                    $query->whereNull('effective_to')->orWhere('effective_to', '>=', $selectedDate->toDateString());
                })
                ->first()?->price ?? 0;

            $status = $this->availabilityService->checkAvailability($court->id, $selectedDate, $slot->id);

            $slotStart = Carbon::parse($selectedDate->toDateString() . ' ' . $slot->start_time);
            if ($selectedDate->isToday() && $slotStart->lte(now())) {
                $status = CourtAvailabilityService::STATUS_MAINTENANCE;
            }

            $availability[$slot->id] = [
                'status' => $status,
                'price' => $price,
            ];
        }

        $dateRange = collect(range(0, min(config('booking.max_days', 30), 20)))
            ->map(fn ($day) => Carbon::today()->addDays($day));

        // A court detail page must only show the court in its route.
        $court->load(['prices' => function ($query) use ($selectedDate) {
            $query->where('status', 'ACTIVE')
                ->where('effective_from', '<=', $selectedDate->toDateString())
                ->where(function ($subQuery) use ($selectedDate) {
                    $subQuery->whereNull('effective_to')
                        ->orWhere('effective_to', '>=', $selectedDate->toDateString());
                });
        }]);
        $scheduleCourts = collect([$court]);

        $scheduleAvailability = [];
        foreach ($scheduleCourts as $scheduleCourt) {
            foreach ($timeSlots as $slot) {
                $price = $scheduleCourt->prices->firstWhere('time_slot_id', $slot->id)?->price ?? 0;
                $status = $this->availabilityService->checkAvailability($scheduleCourt->id, $selectedDate, $slot->id);

                $slotStart = Carbon::parse($selectedDate->toDateString().' '.$slot->start_time);
                if (($selectedDate->isToday() && $slotStart->lte(now())) || $price <= 0) {
                    $status = CourtAvailabilityService::STATUS_MAINTENANCE;
                }

                $scheduleAvailability[$scheduleCourt->id][$slot->id] = compact('status', 'price');
            }
        }

        return view('courts.show', [
            'court' => $court,
            'reviews' => $reviews,
            'rating_stats' => $ratingStats,
            'activePrices' => $activePrices,
            'selectedDate' => $selectedDate,
            'dateRange' => $dateRange,
            'timeSlots' => $timeSlots,
            'availability' => $availability,
            'scheduleCourts' => $scheduleCourts,
            'scheduleAvailability' => $scheduleAvailability,
        ]);
    }

    /**
     * UC16 - Get court availability
     */
    public function availability(CheckAvailabilityRequest $request)
    {
        $court = Court::findOrFail($request->court_id);

        if ($court->status !== 'ACTIVE') {
            return response()->json(['error' => 'Sân không hoạt động'], 400);
        }

        $date = Carbon::parse($request->booking_date);

        // Validate date is within booking window
        $maxDays = config('booking.max_days', 30);
        if ($date < now() || $date > now()->addDays($maxDays)) {
            return response()->json(['error' => 'Ngày đặt không hợp lệ'], 400);
        }

        $availability = $this->availabilityService->getAvailabilityByDate($court->id, $date);

        return response()->json([
            'court_id' => $court->id,
            'court_name' => $court->name,
            'booking_date' => $date->format('Y-m-d'),
            'time_slots' => $availability,
        ]);
    }

    // Remove create, edit, update, destroy methods (not needed for customer)
    public function create() { abort(404); }
    public function edit($id) { abort(404); }
    public function update(Request $request, $id) { abort(404); }
    public function destroy($id) { abort(404); }
    public function store(Request $request) { abort(404); }
}
