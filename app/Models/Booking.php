<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_name', 
        'location', 
        'category', 
        'event_date',
        'start_time', 
        'end_time', 
        'guest_count', 
        'budget', 
        'service_id', 
        'user_id', 
        'status', 
        'venue_id',
        'payment_status',      
        'total_amount',        
        'card_number_last4'    
    ];

    protected $casts = [
        'budget' => 'float',
        'guest_count' => 'integer',
        'event_date' => 'datetime',
        'service_id' => 'integer',
        'user_id' => 'integer',
        'venue_id' => 'integer',
    ];

    public function service() {
        return $this->belongsTo(Vendor::class, 'service_id')->withDefault([
            'name' => 'No Vendor Assigned',
            'business_name' => 'No Vendor Assigned'
        ]);
    }

    public function services()
    {
        return $this->belongsToMany(Vendor::class, 'booking_service', 'booking_id', 'vendor_id');
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }
}