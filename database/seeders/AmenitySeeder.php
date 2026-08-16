<?php

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Seeder;

class AmenitySeeder extends Seeder
{
    public function run(): void
    {
        $amenities = [
            ['name' => 'Điều hòa', 'status' => 'ACTIVE'],
            ['name' => 'Đèn LED', 'status' => 'ACTIVE'],
            ['name' => 'Bãi đỗ xe', 'status' => 'ACTIVE'],
            ['name' => 'Quán cà phê', 'status' => 'ACTIVE'],
            ['name' => 'Phòng tập gym', 'status' => 'ACTIVE'],
            ['name' => 'Nhà vệ sinh sạch sẽ', 'status' => 'ACTIVE'],
            ['name' => 'WIFI miễn phí', 'status' => 'ACTIVE'],
            ['name' => 'Chuỗi cầu mới', 'status' => 'ACTIVE'],
            ['name' => 'Hỗ trợ HLV', 'status' => 'ACTIVE'],
            ['name' => 'Bán dụng cụ thể thao', 'status' => 'ACTIVE'],
        ];

        foreach ($amenities as $amenity) {
            Amenity::create($amenity);
        }
    }
}
