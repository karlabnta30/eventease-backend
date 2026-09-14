<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bundle extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'bundle_name',
        'description',
        'price',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'bundle_service');
    }
}