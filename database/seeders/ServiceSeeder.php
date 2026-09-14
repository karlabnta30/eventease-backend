<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Service;
use App\Models\User;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Find the Vendor we created earlier in the UserSeeder
        $vendor = User::where('role', 'vendor')->first();

        // Safety check: If for some reason the vendor doesn't exist, stop here.
        if (!$vendor) {
            $this->command->error("No Vendor found! Please run UserSeeder first.");
            return;
        }

        // 2. Add a Venue (Fulfills Objective 2: Catalog)
        Service::create([
            'name' => 'Grand Ballroom Venue',
            'description' => 'A luxury space for weddings and corporate events in the heart of the city.',
            'price' => 50000.00,
            'category' => 'Venue',
            'location' => 'Makati, Metro Manila',
            'user_id' => $vendor->id, // Automatically uses the correct Vendor ID
            'is_available' => true,
        ]);

        // 3. Add a Catering service (Fulfills Objective 2: Catalog)
        Service::create([
            'name' => 'Gourmet Catering Services',
            'description' => 'Full-course Filipino and International meals for up to 100 guests.',
            'price' => 25000.00,
            'category' => 'Catering',
            'location' => 'Quezon City, Metro Manila',
            'user_id' => $vendor->id,
            'is_available' => true,
        ]);
    }
}