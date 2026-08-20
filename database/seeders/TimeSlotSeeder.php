<?php

namespace Database\Seeders;

use App\Models\TimeSlot;
use Illuminate\Database\Seeder;

class TimeSlotSeeder extends Seeder
{
    public function run(): void
    {
        // Morning slots (6:00 - 12:00)
        for ($hour = 6; $hour < 12; $hour++) {
            TimeSlot::updateOrCreate(['start_time' => sprintf('%02d:00:00', $hour), 'end_time' => sprintf('%02d:00:00', $hour + 1)], [
                'name' => sprintf('%02d:00 - %02d:00', $hour, $hour + 1),
                'start_time' => sprintf('%02d:00:00', $hour),
                'end_time' => sprintf('%02d:00:00', $hour + 1),
                'duration' => 60,
                'status' => 'ACTIVE',
            ]);
        }

        // Afternoon slots (12:00 - 18:00)
        for ($hour = 12; $hour < 18; $hour++) {
            TimeSlot::updateOrCreate(['start_time' => sprintf('%02d:00:00', $hour), 'end_time' => sprintf('%02d:00:00', $hour + 1)], [
                'name' => sprintf('%02d:00 - %02d:00', $hour, $hour + 1),
                'start_time' => sprintf('%02d:00:00', $hour),
                'end_time' => sprintf('%02d:00:00', $hour + 1),
                'duration' => 60,
                'status' => 'ACTIVE',
            ]);
        }

        // Evening slots (18:00 - 23:00)
        for ($hour = 18; $hour < 23; $hour++) {
            TimeSlot::updateOrCreate(['start_time' => sprintf('%02d:00:00', $hour), 'end_time' => sprintf('%02d:00:00', $hour + 1)], [
                'name' => sprintf('%02d:00 - %02d:00', $hour, $hour + 1),
                'start_time' => sprintf('%02d:00:00', $hour),
                'end_time' => sprintf('%02d:00:00', $hour + 1),
                'duration' => 60,
                'status' => 'ACTIVE',
            ]);
        }
    }
}
