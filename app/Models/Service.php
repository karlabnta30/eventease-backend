<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
    'user_id',       // <--- Make sure this is here
    'vendor_id',
    'name',
    'description',
    'price',
    'category',
    'location',
    'is_available',
];

    public function bundles()
    {
        return $this->belongsToMany(Bundle::class, 'bundle_service');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}