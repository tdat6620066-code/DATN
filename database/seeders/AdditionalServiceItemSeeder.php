<?php

namespace Database\Seeders;

use App\Models\ServiceItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdditionalServiceItemSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            if (! ServiceItem::where('code', 'LOCKER_RENTAL')->exists()) {
                ServiceItem::where('code', 'SPORT_TOWEL_RENTAL')->update(['code' => 'LOCKER_RENTAL', 'name' => 'Thuê tủ']);
            }
            foreach ([
                ['code' => 'ELECTROLYTE', 'name' => 'Nước điện giải', 'category' => 'DRINK', 'price' => 20000],
                ['code' => 'RACKET_GRIP', 'name' => 'Quấn cán vợt', 'category' => 'PRODUCT', 'price' => 30000],
                ['code' => 'LOCKER_RENTAL', 'name' => 'Thuê tủ', 'category' => 'RENTAL', 'price' => 15000],
            ] as $item) {
                ServiceItem::firstOrCreate(['code' => $item['code']], $item + ['stock' => 0, 'is_active' => true]);
            }
        });
    }
}
