<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | UC05
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $user = auth()->user()->loadCount('bookings');

        $bookings = Booking::where('user_id', $user->id)
            ->with('bookingDetails.court', 'bookingDetails.timeSlot', 'payment')
            ->when($request->filled('status'), function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->when($request->filled('date_from'), function ($query) use ($request) {
                return $query->whereDate('created_at', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                return $query->whereDate('created_at', '<=', $request->date_to);
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('profile.index', [
            'user' => $user,
            'bookings' => $bookings,
            'filters' => [
                'status' => $request->status,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
            ],
        ]);
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = auth()->user();

        $data = [
            'name' => $request->name,
            'phone' => $request->phone,
            'address' => $request->address,
        ];

        if ($request->hasFile('avatar')) {

            if (
                $user->avatar &&
                Storage::disk('public')->exists($user->avatar)
            ) {
                Storage::disk('public')->delete(
                    $user->avatar
                );
            }

            $data['avatar'] = $request
                ->file('avatar')
                ->store('avatars', 'public');
        }

        $user->update($data);

        return back()->with(
            'success',
            'Thông tin cá nhân đã được cập nhật.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UC06
    |--------------------------------------------------------------------------
    */

    public function showChangePassword()
    {
        return view('profile.change-password');
    }

    public function changePassword(
        ChangePasswordRequest $request
    ) {
        $user = auth()->user();

        $user->update([
            'password' => $request->password,
        ]);

        return back()->with(
            'success',
            'Đổi mật khẩu thành công.'
        );
    }
}