<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Receipt extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'file_id',
        'merchant_id',
        'user_id',
        'category_id',
        'receipt_date',
        'tax_amount',
        'total_amount',
        'currency',
        'receipt_category',
        'receipt_description',
        'receipt_data',
    ];

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function lineItems()
    {
        return $this->hasMany(LineItem::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        $this->load(['merchant', 'lineItems']);

        $array = [
            'id' => $this->id,
            'receipt_date' => $this->receipt_date,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'receipt_category' => $this->receipt_category,
            'receipt_description' => $this->receipt_description,
            'merchant_name' => $this->merchant?->name,
            'merchant_address' => $this->merchant?->address,
            'merchant_vat_id' => $this->merchant?->vat_id,
            'line_items' => $this->lineItems->map(function ($item) {
                return [
                    'description' => $item->text,
                    'sku' => $item->sku,
                    'quantity' => $item->qty,
                    'price' => $item->price,
                ];
            })->toArray(),
            'url' => route('receipts.show', $this->id),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];

        // Add raw receipt data if available
        if ($this->receipt_data) {
            $array['raw_data'] = json_decode($this->receipt_data, true);
        }

        return $array;
    }
}
