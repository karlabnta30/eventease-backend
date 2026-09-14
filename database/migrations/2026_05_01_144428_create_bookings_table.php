<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This creates the table with the necessary base columns.
     */
    public function up(): void
{
    Schema::create('bookings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->foreignId('service_id')->nullable()->constrained()->onDelete('cascade');
        $table->string('event_name'); 
        $table->string('location');   
        $table->string('category');  
        $table->dateTime('event_date'); 
        $table->integer('guest_count'); 
        $table->decimal('budget', 15, 2); 
        $table->string('status')->default('pending');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     * This is used when you roll back or refresh.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};