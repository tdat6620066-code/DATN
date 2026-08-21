<?php

namespace Database\Seeders;

use App\Models\ServiceItem;
use Illuminate\Database\Seeder;

class ServiceItemSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code'=>'RACKET','name'=>'Thuê vợt','category'=>'RENTAL','price'=>30000,'stock'=>30],
            ['code'=>'SHOES','name'=>'Thuê giày','category'=>'RENTAL','price'=>40000,'stock'=>20],
            ['code'=>'SHUTTLE','name'=>'Cầu lông','category'=>'PRODUCT','price'=>25000,'stock'=>100],
            ['code'=>'WATER','name'=>'Nước uống','category'=>'DRINK','price'=>15000,'stock'=>100],
            ['code'=>'TOWEL','name'=>'Khăn lạnh','category'=>'PRODUCT','price'=>10000,'stock'=>100],
        ] as $item) ServiceItem::updateOrCreate(['code'=>$item['code']], $item+['is_active'=>true]);
    }
}
