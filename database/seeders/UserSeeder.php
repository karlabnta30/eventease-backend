<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create an Admin (For Objective 7: Manage System Records)
        User::create([
            'name' => 'Admin Karl',
            'email' => 'admin@eventease.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'contact_number' => '09123456789',
            'address' => 'Adamson University, Manila',
        ]);

        // Create a Vendor (For Objective 2 & 7: Manage Listings)
        User::create([
            'name' => 'Event Pro Vendor',
            'email' => 'vendor@eventease.com',
            'password' => Hash::make('password123'),
            'role' => 'vendor',
            'contact_number' => '09987654321',
            'address' => 'Makati City',
        ]);

        // Create a Client (For Objective 3: Bookings)
        User::create([
            'name' => 'Happy Client',
            'email' => 'client@eventease.com',
            'password' => Hash::make('password123'),
            'role' => 'client',
            'contact_number' => '09555555555',
            'address' => 'Quezon City',
        ]);
    }
}