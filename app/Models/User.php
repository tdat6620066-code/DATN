<?php

namespace App\Models;

use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements CanResetPasswordContract
{
    use HasFactory, Notifiable, CanResetPassword;

    /**
     * Các trường được phép mass assignment.
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'email_verified_at',
        'avatar',
        'address',
        'google_id',
        'last_login_at',
    ];

    /**
     * Các trường không được hiển thị khi trả dữ liệu.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Ép kiểu dữ liệu.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',

            // Laravel 12 sẽ tự hash password
            'password' => 'hashed',

            'permissions' => 'array',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Danh sách sân yêu thích.
     */
    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * Danh sách booking.
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Danh sách thông báo.
     */
    public function userNotifications()
    {
        return $this->hasMany(Notification::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    /**
     * Kiểm tra Admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }

    /**
     * Kiểm tra Customer.
     */
    public function isCustomer(): bool
    {
        return $this->role === 'CUSTOMER';
    }

    /**
     * Kiểm tra Employee.
     */
    public function isEmployee(): bool
    {
        return $this->role === 'EMPLOYEE';
    }

    /**
     * Kiểm tra tài khoản bị khóa.
     */
    public function isLocked(): bool
    {
        return $this->status === 'LOCKED';
    }

    /**
     * Kiểm tra quyền.
     *
     * Admin có toàn quyền.
     * Các role khác kiểm tra trong mảng permissions.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->role === 'ADMIN') {
            return true;
        }

        return in_array(
            $permission,
            $this->permissions ?? [],
            true
        );
    }
}