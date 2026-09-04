<?php

namespace App\Services;

use App\Events\CustomerNotificationCreated;
use App\Models\Notification;
use App\Models\SystemAnnouncement;
use App\Models\User;

class AnnouncementService
{
    public function deliver(SystemAnnouncement $announcement): void
    {
        if ($announcement->status === 'SENT') return;
        $roles = match ($announcement->audience) { 'CUSTOMER' => ['CUSTOMER'], 'EMPLOYEE' => ['EMPLOYEE', 'ADMIN'], default => ['CUSTOMER', 'EMPLOYEE', 'ADMIN'] };
        $recipients = User::query()->whereIn('role', $roles);
        match ($announcement->target_type) {
            'SELECTED' => $recipients->whereIn('id', $announcement->target_user_ids ?? []),
            'COURT' => $recipients->whereHas('bookings.bookingDetails', fn ($query) => $query->where('court_id', $announcement->court_id)),
            'AREA' => $recipients->whereHas('bookings.bookingDetails.court', fn ($query) => $query->where('address', 'like', '%'.$announcement->area.'%')),
            default => $recipients,
        };
        $recipients->select('id')->chunkById(500, function ($users) use ($announcement) {
            foreach ($users as $user) {
                $notification = Notification::firstOrCreate(['unique_key' => "announcement:{$announcement->id}:{$user->id}"], [
                    'user_id' => $user->id, 'announcement_id' => $announcement->id,
                    'title' => '📢 Thông báo hệ thống: '.$announcement->title,
                    'content' => $announcement->content, 'type' => 'ADMIN', 'action_url' => $announcement->action_url, 'is_read' => false,
                ]);
                if ($notification->wasRecentlyCreated) CustomerNotificationCreated::dispatch($notification);
            }
        });
        $announcement->update(['status' => 'SENT', 'sent_at' => now()]);
    }
}
