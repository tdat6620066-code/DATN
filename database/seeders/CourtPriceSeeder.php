<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\TimeSlot;
use App\Models\CourtPrice;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class CourtPriceSeeder extends Seeder
{
    public function run(): void
    {
        $courts = Court::all();
        $timeSlots = TimeSlot::all();

        foreach ($courts as $court) {
            foreach ($timeSlots as $timeSlot) {
                // Different pricing for different courts and time slots
                $basePrice = 100000; // Base price in VND

                // Peak hours (18-23) cost more
                $hour = (int) explode(':', $timeSlot->start_time)[0];
                if ($hour >= 18) {
                    $multiplier = 1.5;
                } elseif ($hour >= 12) {
                    $multiplier = 1.2;
                } else {
                    $multiplier = 1.0;
                }

                // VIP courts cost more
                if ($court->id % 3 === 0) {
                    $multiplier *= 1.3;
                }

                $price = $basePrice * $multiplier;

                CourtPrice::updateOrCreate(
                    [
                        'court_id' => $court->id,
                        'time_slot_id' => $timeSlot->id,
                        'day_type' => 'WEEKDAY',
                    ],
                    [
                        'price' => $price,
                        'effective_from' => Carbon::now()->startOfDay(),
                        'effective_to' => null,
                        'status' => 'ACTIVE',
                    ]
                );
            }
        }
    }
}