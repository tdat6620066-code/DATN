<?php

namespace App\Http\Controllers;

use App\Models\Notification;

class NotificationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | UC09
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $notifications = Notification::where(
            'user_id',
            auth()->id()
        )
            ->latest()
            ->paginate(15);

        return view(
            'notifications.index',
            compact('notifications')
        );
    }

    public function markAsRead(Notification $notification)
    {
        abort_if(
            $notification->user_id !== auth()->id(),
            403
        );

        $notification->update([
            'is_read' => true,
        ]);

        return back();
    }

    public function markAllAsRead()
    {
        Notification::where(
            'user_id',
            auth()->id()
        )->update([
            'is_read' => true,
        ]);

        return back()->with(
            'success',
            'Đã đánh dấu tất cả thông báo là đã đọc.'
        );
    }
}