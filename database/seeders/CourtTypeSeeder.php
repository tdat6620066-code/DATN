<?php

namespace Database\Seeders;

use App\Models\CourtType;
use Illuminate\Database\Seeder;

class CourtTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Sân cầu lông tiêu chuẩn',
                'description' => 'Sân cầu lông tiêu chuẩn 17m x 8.17m',
                'status' => 'ACTIVE',
            ],
            [
                'name' => 'Sân cầu lông nhân tạo',
                'description' => 'Sân cầu lông trên bề mặt nhân tạo',
                'status' => 'ACTIVE',
            ],
            [
                'name' => 'Sân cầu lông trong nhà',
                'description' => 'Sân cầu lông có lợp mái, điều hòa',
                'status' => 'ACTIVE',
            ],
        ];

        foreach ($types as $type) {
            CourtType::updateOrCreate(['name' => $type['name']], $type);
        }
    }
}