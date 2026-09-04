<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Support\Str;

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
            'read_at' => $notification->read_at ?? now(),
        ]);

        return back();
    }

    public function markAllAsRead()
    {
        Notification::where(
            'user_id',
            auth()->id()
        )->where('is_read', false)->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return back()->with(
            'success',
            'Đã đánh dấu tất cả thông báo là đã đọc.'
        );
    }

    public function open(Notification $notification)
    {
        abort_if($notification->user_id !== auth()->id(), 403);
        $notification->update([
            'is_read' => true,
            'read_at' => $notification->read_at ?? now(),
            'clicked_at' => $notification->clicked_at ?? now(),
        ]);

        $destination = $notification->action_url;
        if (! $destination || ! Str::startsWith($destination, url('/'))) {
            return redirect()->route('notifications.index');
        }
        return redirect()->to($destination);
    }
}
