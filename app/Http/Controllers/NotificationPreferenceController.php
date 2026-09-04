<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function edit(Request $request)
    {
        abort_unless($request->user()->role === 'CUSTOMER', 403);
        return view('profile.notification-settings', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->role === 'CUSTOMER', 403);
        $data = $request->validate([
            'reminder' => ['nullable', 'boolean'],
            'promotion' => ['nullable', 'boolean'],
            'email' => ['nullable', 'boolean'],
        ]);

        $request->user()->update(['notification_preferences' => [
            'reminder' => (bool) ($data['reminder'] ?? false),
            'promotion' => (bool) ($data['promotion'] ?? false),
            'email' => (bool) ($data['email'] ?? false),
        ]]);

        return back()->with('success', 'Đã lưu cài đặt thông báo.');
    }
}
