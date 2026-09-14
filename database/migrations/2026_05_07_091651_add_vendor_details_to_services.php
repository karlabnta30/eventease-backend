<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVendorDetailsToServices extends Migration
{
    public function up()
    {
        Schema::table('services', function (Blueprint $table) {
            // 1. Link to the Vendor (User)
            if (!Schema::hasColumn('services', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable()->constrained('users')->onDelete('cascade');
            }

            // 2. The Vendor's asking price (Renamed to service_fee to protect your budget data)
            if (!Schema::hasColumn('services', 'service_fee')) {
                $table->decimal('service_fee', 15, 2)->default(0)->after('name');
            }

            // 3. Service Details
            if (!Schema::hasColumn('services', 'description')) {
                $table->text('description')->nullable();
            }

            if (!Schema::hasColumn('services', 'image_url')) {
                $table->string('image_url')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('services', function (Blueprint $table) {
            if (Schema::hasColumn('services', 'vendor_id')) {
                $table->dropForeign(['vendor_id']);
                $table->dropColumn('vendor_id');
            }
            $table->dropColumn(['service_fee', 'description', 'image_url']);
        });
    }
}