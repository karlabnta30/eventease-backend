<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('bundles', function (Blueprint $table) {
        $table->integer('min_pax')->nullable()->default(15);
        $table->integer('max_pax')->nullable()->default(100);
    });
}

public function down()
{
    Schema::table('bundles', function (Blueprint $table) {
        $table->dropColumn(['min_pax', 'max_pax']);
    });
}
};
