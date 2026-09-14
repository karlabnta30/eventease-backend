<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'service_name',
        'description',
        'price',
    ];

    public function bundles()
    {
        return $this->belongsToMany(Bundle::class, 'bundle_service');
    }
}