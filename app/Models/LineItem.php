<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

/**
 * App\Models\LineItem
 *
 * @property int $id
 * @property int $receipt_id
 * @property string|null $text
 * @property string|null $sku
 * @property float|null $qty
 * @property float|null $price
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Receipt $receipt
 */
class LineItem extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = ['receipt_id', 'vendor_id', 'text', 'sku', 'qty', 'price', 'total'];

    /**
     * Get the receipt that owns the line item.
     */
    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
