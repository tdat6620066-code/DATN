<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
<<<<<<< HEAD
        'status',
        'avatar',
        'address',
        'google_id',
        'last_login_at',
=======
        'phone',
        'status',
        'permissions',
        'refund_approval_limit',
>>>>>>> 9790fd584874111b4e4d91e45e981ae25b3deaae
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'refund_approval_limit' => 'decimal:2',
            'permissions' => 'array',
        ];
    }

<<<<<<< HEAD
    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'CUSTOMER';
    }

    public function isLocked(): bool
    {
        return $this->status === 'LOCKED';
    }
}
=======
    // Relationships
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->role === 'ADMIN'
            || ($this->role === 'EMPLOYEE' && $this->permissions === null)
            || in_array($permission, $this->permissions ?? [], true);
    }
}
>>>>>>> 9790fd584874111b4e4d91e45e981ae25b3deaae
