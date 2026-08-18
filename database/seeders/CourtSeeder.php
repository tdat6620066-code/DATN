<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\CourtType;
use App\Models\Amenity;
use Illuminate\Database\Seeder;

class CourtSeeder extends Seeder
{
    public function run(): void
    {
        $courtTypes = CourtType::all();
        $amenities = Amenity::all();

        $courts = [
            [
                'name' => 'Sân số 1 - Tiêu chuẩn',
                'code' => 'COURT001',
                'court_type_id' => $courtTypes->first()->id,
                'description' => 'Sân cầu lông tiêu chuẩn, điều hòa, đèn LED',
                'status' => 'ACTIVE',
            ],
            [
                'name' => 'Sân số 2 - Tiêu chuẩn',
                'code' => 'COURT002',
                'court_type_id' => $courtTypes->first()->id,
                'description' => 'Sân cầu lông tiêu chuẩn, điều hòa, đèn LED',
                'status' => 'ACTIVE',
            ],
            [
                'name' => 'Sân số 3 - VIP',
                'code' => 'COURT003',
                'court_type_id' => $courtTypes->where('name', 'Sân cầu lông trong nhà')->first()->id,
                'description' => 'Sân VIP với điều hòa đầy đủ, đèn LED chuyên nghiệp',
                'status' => 'ACTIVE',
            ],
            [
                'name' => 'Sân số 4 - Ngoài trời',
                'code' => 'COURT004',
                'court_type_id' => $courtTypes->where('name', 'Sân cầu lông nhân tạo')->first()->id,
                'description' => 'Sân ngoài trời với mặt sân nhân tạo',
                'status' => 'ACTIVE',
            ],
            [
                'name' => 'Sân số 5 - Tiêu chuẩn',
                'code' => 'COURT005',
                'court_type_id' => $courtTypes->first()->id,
                'description' => 'Sân cầu lông tiêu chuẩn, điều hòa, đèn LED',
                'status' => 'ACTIVE',
            ],
            [
                'name' => 'Sân số 6 - Huấn luyện',
                'code' => 'COURT006',
                'court_type_id' => $courtTypes->where('name', 'Sân cầu lông trong nhà')->first()->id,
                'description' => 'Sân huấn luyện có hỗ trợ HLV',
                'status' => 'ACTIVE',
            ],
        ];

        foreach ($courts as $courtData) {
            $court = Court::create($courtData);

            // Attach random amenities (3-7 per court)
            $randomAmenities = $amenities->random(rand(3, 7));
            $court->amenities()->attach($randomAmenities->pluck('id'));
        }
    }
}
