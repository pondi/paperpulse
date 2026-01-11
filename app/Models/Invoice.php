<?php

namespace App\Models;

use App\Contracts\Taggable;
use App\Traits\BelongsToUser;
use App\Traits\ExtractableEntity as ExtractableEntityTrait;
use App\Traits\ShareableModel;
use App\Traits\TaggableModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Invoice extends Model implements Taggable
{
    use BelongsToUser;
    use ExtractableEntityTrait;
    use HasFactory;
    use Searchable;
    use ShareableModel;
    use TaggableModel;

    protected $fillable = [
        'file_id',
        'user_id',
        'merchant_id',
        'category_id',
        'invoice_number',
        'invoice_type',
        'from_name',
        'from_address',
        'from_vat_number',
        'from_email',
        'from_phone',
        'to_name',
        'to_address',
        'to_vat_number',
        'to_email',
        'to_phone',
        'invoice_date',
        'due_date',
        'delivery_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'shipping_amount',
        'total_amount',
        'amount_paid',
        'amount_due',
        'currency',
        'payment_method',
        'payment_status',
        'payment_terms',
        'purchase_order_number',
        'reference_number',
        'notes',
        'invoice_data',
    ];

    protected $casts = [
        'invoice_data' => 'array',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'delivery_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_due' => 'decimal:2',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    public function getEntityType(): string
    {
        return 'invoice';
    }

    protected function getShareableType(): string
    {
        return 'invoice';
    }

    protected function getTaggableType(): string
    {
        return 'invoice';
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing(['merchant', 'category', 'tags', 'lineItems']);

        return [
            'user_id' => $this->user_id,
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'invoice_type' => $this->invoice_type,
            'invoice_date' => $this->invoice_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'total_amount' => $this->total_amount,
            'payment_status' => $this->payment_status,
            'merchant_name' => $this->merchant?->name,
            'category_name' => $this->category?->name,
            'tags' => $this->tags?->pluck('name')->toArray() ?? [],
            'line_items' => $this->lineItems->map(function ($item) {
                return [
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_amount' => $item->total_amount,
                ];
            })->toArray(),
        ];
    }
}
