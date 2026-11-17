<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create the ultimate Super Admin (no school_id needed, they own the platform)
        User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@erp.com',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'school_id' => null,
        ]);
    }
}
