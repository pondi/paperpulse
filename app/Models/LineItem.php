<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class LineItem extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = ['text', 'sku', 'qty', 'price'];

    // In Receipt model
    public function lineItems()
    {
        return $this->hasMany(LineItem::class);
    }

    // In LineItem model
    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
    }
}
