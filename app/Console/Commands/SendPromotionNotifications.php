<?php

namespace App\Console\Commands;

use App\Events\CustomerNotificationCreated;
use App\Models\Notification;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Console\Command;

class SendPromotionNotifications extends Command
{
    protected $signature = 'promotions:send-notifications';
    protected $description = 'Gửi các khuyến mãi đang hoạt động đến khách hàng';

    public function handle(): int
    {
        Promotion::where('status', 'ACTIVE')
            ->where(fn ($query) => $query->whereNull('start_at')->orWhere('start_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('end_at')->orWhere('end_at', '>=', now()))
            ->chunkById(50, function ($promotions) {
                User::where('role', 'CUSTOMER')->select('id')->chunkById(500, function ($users) use ($promotions) {
                    foreach ($promotions as $promotion) {
                        foreach ($users as $user) {
                            if (! $user->notificationEnabled('promotion')) continue;

                            $notification = Notification::firstOrCreate(
                                ['unique_key' => "promotion:{$promotion->id}:{$user->id}"],
                                [
                                    'user_id' => $user->id,
                                    'title' => '🎁 Khuyến mãi: '.$promotion->title,
                                    'content' => $promotion->description ?: 'Ưu đãi mới đang áp dụng tại SmashZone.',
                                    'type' => 'PROMOTION',
                                    'action_url' => route('home').'#offers',
                                    'is_read' => false,
                                ]
                            );
                            if ($notification->wasRecentlyCreated) CustomerNotificationCreated::dispatch($notification);
                        }
                    }
                });
            });

        return self::SUCCESS;
    }
}
