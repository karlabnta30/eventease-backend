<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Palitan ang 'vendors' ng 'services' kung yun ang aktwal na table name sa iyong database
        Schema::table('vendors', function (Blueprint $table) {
            $table->unsignedInteger('min_pax')->nullable()->after('price');
            $table->unsignedInteger('max_pax')->nullable()->after('min_pax');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['min_pax', 'max_pax']);
        });
    }
};