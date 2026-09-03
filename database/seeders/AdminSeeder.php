<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

/**
 * Guarantees one admin account exists (there is no admin self-registration endpoint).
 * Override the credentials with ADMIN_EMAIL / ADMIN_PASSWORD and change them before go-live.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        if (Admin::query()->exists()) {
            return;
        }

        Admin::create([
            'email' => env('ADMIN_EMAIL', 'admin@store.com'),
            'password' => env('ADMIN_PASSWORD', 'admin123'),
            'first_name' => 'Admin',
            'last_name' => 'User',
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }
}
