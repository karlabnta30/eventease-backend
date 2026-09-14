<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use HasFactory;

    // Forces the model to use the table shown in your MySQL screenshot
    protected $table = 'services';

    protected $fillable = [
        'user_id', 
        'name', 
        'service_fee', 
        'category', 
        'price', 
        'location', 
        'description', 
        'is_available',
        'vendor_id',
        'image_url'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}