<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | UC05
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return view('profile.index', [
            'user' => auth()->user(),
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