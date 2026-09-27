<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_service', function (Blueprint $table) {
            // Drop the old vendor foreign key and column if they exist
            if (Schema::hasColumn('booking_service', 'vendor_id')) {
                $table->dropForeign(['vendor_id']);
                $table->dropColumn('vendor_id');
            }
            
            // Add the correct service_id foreign key column
            if (!Schema::hasColumn('booking_service', 'service_id')) {
                $table->foreignId('service_id')->after('booking_id')->constrained('services')->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('booking_service', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropColumn('service_id');
            $table->foreignId('vendor_id')->constrained('vendors')->onDelete('cascade');
        });
    }
};