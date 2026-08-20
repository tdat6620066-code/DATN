<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Nội dung đánh giá mẫu (tiếng Việt, thực tế).
     */
    private array $reviewContents = [
        'Sân sạch sẽ, ánh sáng tốt, nhân viên hỗ trợ nhiệt tình.',
        'Mặt sân đẹp, độ nhám chuẩn, đánh rất bám cầu.',
        'Không gian thoáng mát, có chỗ gửi xe và nghỉ ngơi tiện lợi.',
        'Đặt sân nhanh qua mạng, check-in bằng QR rất thuận tiện.',
        'Sân đẹp hơn mong đợi, giá hợp lý, sẽ quay lại thường xuyên.',
        'Ánh đèn LED sáng đều, không chói mắt khi đỡ cầu cao.',
        'Có quầy nước và dụng cụ cho thuê, rất chu đáo.',
        'Mặt sân mới, phòng máy lạnh khi đổi giờ giải lao cực mát.',
        'Chỗ để xe rộng, an ninh tốt, cảm giác yên tâm khi chơi.',
        'Nhân viên nhắc giờ linh hoạt, được chơi thêm khi sân trống.',
    ];

    /**
     * Seed đánh giá (APPROVED) cho từng sân.
     */
    public function run(): void
    {
        $courts = Court::all();

        if ($courts->isEmpty()) {
            $this->command?->warn('Chưa có sân nào. Hãy chạy CourtSeeder trước.');

            return;
        }

        // Danh sách khách hàng dùng để ghi đánh giá (cố định để chạy lại không bị trùng email).
        $reviewers = $this->createReviewers();

        foreach ($courts as $court) {
            // 3 - 6 đánh giá cho mỗi sân.
            $reviewCount = random_int(3, 6);

            for ($i = 0; $i < $reviewCount; $i++) {
                $reviewer = $reviewers[$i % count($reviewers)];

                $booking = $this->createBooking($court, $reviewer, $i);

                $review = Review::firstOrCreate(
                    ['booking_id' => $booking->id],
                    [
                        'user_id' => $reviewer->id,
                        'court_id' => $court->id,
                        'rating' => random_int(4, 5),
                        'content' => $this->reviewContents[array_rand($this->reviewContents)],
                        'status' => 'APPROVED',
                    ]
                );

                // Đảm bảo review gắn đúng sân và luôn được duyệt khi chạy lại.
                $review->update([
                    'court_id' => $court->id,
                    'status' => 'APPROVED',
                ]);
            }
        }

        $this->command?->info('Đã seed đánh giá cho '.$courts->count().' sân.');
    }

    /**
     * Tạo (hoặc lấy) các khách hàng dùng để viết đánh giá.
     *
     * @return array<int, User>
     */
    private function createReviewers(): array
    {
        $profiles = [
            ['email' => 'minh.nguyen@example.com', 'name' => 'Nguyễn Minh', 'phone' => '0911110001'],
            ['email' => 'thao.tran@example.com', 'name' => 'Trần Thu Thảo', 'phone' => '0911110002'],
            ['email' => 'duc.pham@example.com', 'name' => 'Phạm Minh Đức', 'phone' => '0911110003'],
            ['email' => 'linh.hoang@example.com', 'name' => 'Hoàng Khánh Linh', 'phone' => '0911110004'],
            ['email' => 'quan.le@example.com', 'name' => 'Lê Anh Quân', 'phone' => '0911110005'],
            ['email' => 'huyen.vu@example.com', 'name' => 'Vũ Thanh Huyền', 'phone' => '0911110006'],
        ];

        $reviewers = [];

        foreach ($profiles as $profile) {
            $reviewers[] = User::firstOrCreate(
                ['email' => $profile['email']],
                [
                    'name' => $profile['name'],
                    'password' => bcrypt('password'),
                    'role' => 'CUSTOMER',
                    'phone' => $profile['phone'],
                ]
            );
        }

        return $reviewers;
    }

    /**
     * Tạo booking COMPLETED để đánh giá có booking_id hợp lệ.
     * Review chỉ được viết cho booking đã hoàn thành.
     */
    private function createBooking(Court $court, User $reviewer, int $index): Booking
    {
        $bookingCode = 'BK-REV-'.$court->id.'-'.($index + 1);

        $booking = Booking::firstOrCreate(
            ['booking_code' => $bookingCode],
            [
                'user_id' => $reviewer->id,
                'subtotal' => 150000,
                'discount' => 0,
                'total_amount' => 150000,
                'status' => 'COMPLETED',
                'payment_status' => 'PAID',
                'confirmed_at' => now()->subDays(rand(3, 30)),
                'checked_in_at' => now()->subDays(rand(2, 20)),
                'checked_out_at' => now()->subDays(rand(1, 15)),
            ]
        );

        // Đảm bảo booking thuộc đúng người đánh giá khi chạy lại.
        $booking->update(['user_id' => $reviewer->id]);

        return $booking;
    }
}