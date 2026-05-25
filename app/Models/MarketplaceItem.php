<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceItem extends Model
{
    protected $fillable = ['name', 'price', 'currency', 'image', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
