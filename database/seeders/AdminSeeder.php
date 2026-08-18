<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@smashzone.test'], [
            'name' => 'Quản trị SmashZone',
            'password' => bcrypt('password'),
            'role' => 'ADMIN',
            'refund_approval_limit' => 999999999,
        ]);
    }
}
