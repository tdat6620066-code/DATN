<?php

namespace App\Console\Commands;

use App\Models\SystemAnnouncement;
use App\Services\AnnouncementService;
use Illuminate\Console\Command;

class SendScheduledAnnouncements extends Command
{
    protected $signature = 'announcements:send-scheduled';
    protected $description = 'Gửi các thông báo Admin đã đến lịch';

    public function handle(AnnouncementService $service): int
    {
        SystemAnnouncement::where('status', 'SCHEDULED')
            ->where('scheduled_at', '<=', now())
            ->chunkById(100, fn ($items) => $items->each(fn ($item) => $service->deliver($item)));

        return self::SUCCESS;
    }
}
