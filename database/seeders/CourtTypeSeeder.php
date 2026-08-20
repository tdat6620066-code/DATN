<?php

namespace Database\Seeders;

use App\Models\CourtType;
use Illuminate\Database\Seeder;

class CourtTypeSeeder extends Seeder
{
    public function run(): void
    {
        CourtType::updateOrCreate(['name' => 'Sân cầu lông tiêu chuẩn'], [
            'description' => 'Sân cầu lông tiêu chuẩn 17m x 8.17m',
            'status' => 'ACTIVE',
        ]);

        CourtType::updateOrCreate(['name' => 'Sân cầu lông nhân tạo'], [
            'description' => 'Sân cầu lông trên bề mặt nhân tạo',
            'status' => 'ACTIVE',
        ]);

        CourtType::updateOrCreate(['name' => 'Sân cầu lông trong nhà'], [
            'description' => 'Sân cầu lông có lợp mái, điều hòa',
            'status' => 'ACTIVE',
        ]);
    }
}