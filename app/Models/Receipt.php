<?php

namespace App\Models;

use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

/**
 * App\Models\Receipt
 *
 * @property int $id
 * @property int $file_id
 * @property int $user_id
 * @property int|null $merchant_id
 * @property int|null $category_id
 * @property \Carbon\Carbon|null $receipt_date
 * @property float|null $tax_amount
 * @property float|null $total_amount
 * @property string|null $currency
 * @property string|null $receipt_category
 * @property string|null $receipt_description
 * @property array|null $receipt_data
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\File $file
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Merchant|null $merchant
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\LineItem[] $lineItems
 * @property-read \App\Models\Category|null $category
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Tag[] $tags
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Receipt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Receipt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Receipt query()
 */
class Receipt extends Model
{
    use BelongsToUser;
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

    protected $casts = [
        'receipt_data' => 'array',
        'receipt_date' => 'date',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
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

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'file_tags', 'file_id', 'tag_id')
            ->wherePivot('file_type', 'receipt')
            ->withTimestamps();
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
