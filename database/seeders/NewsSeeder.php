<?php

namespace Database\Seeders;

use App\Models\News;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class NewsSeeder extends Seeder
{
    public function run(): void
    {
        News::updateOrCreate(['slug' => 'smashzone-mobile-app-launch'], [
            'title' => 'SmashZone ra mắt ứng dụng mobile mới',
            'content' => 'Ứng dụng mobile SmashZone mới với giao diện hiện đại, tính năng đặt sân nhanh chóng hơn 50%',
            'published_at' => Carbon::now()->subDays(5),
            'status' => 'PUBLISHED',
        ]);

        News::updateOrCreate(['slug' => 'new-branch-hanoi'], [
            'title' => 'Khai trương chi nhánh mới tại Hà Nội',
            'content' => 'Chi nhánh mới của SmashZone tại quận Cầu Giấy, Hà Nội đã chính thức khai trương với 8 sân cầu lông tiêu chuẩn',
            'published_at' => Carbon::now()->subDays(10),
            'status' => 'PUBLISHED',
        ]);

        News::updateOrCreate(['slug' => 'badminton-tournament-2024'], [
            'title' => 'Giải đấu cầu lông mở rộng toàn quốc 2024',
            'content' => 'SmashZone tài trợ giải đấu cầu lông mở rộng toàn quốc với tổng giải thưởng 500 triệu đồng',
            'published_at' => Carbon::now()->subDays(15),
            'status' => 'PUBLISHED',
        ]);

        News::updateOrCreate(['slug' => 'ac-system-upgrade'], [
            'title' => 'Nâng cấp hệ thống điều hòa',
            'content' => 'Tất cả các sân của SmashZone đã được nâng cấp hệ thống điều hòa đẳng cấp quốc tế',
            'published_at' => Carbon::now()->subDays(20),
            'status' => 'PUBLISHED',
        ]);

        News::updateOrCreate(['slug' => 'coaching-program'], [
            'title' => 'Program huấn luyện cầu lông chuyên nghiệp',
            'content' => 'SmashZone mở chương trình huấn luyện cầu lông với các HLV quốc tế',
            'published_at' => Carbon::now()->subDays(25),
            'status' => 'PUBLISHED',
        ]);

        News::updateOrCreate(['slug' => 'customer-achievement'], [
            'title' => 'Thành tích nổi bật của khách hàng SmashZone',
            'content' => 'Nhiều vận động viên tập luyện tại SmashZone đã đạt các thành tích cao trong các giải đấu quốc tế',
            'published_at' => Carbon::now()->subDays(30),
            'status' => 'PUBLISHED',
        ]);
    }
}
