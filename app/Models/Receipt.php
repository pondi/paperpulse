<?php

namespace App\Models;

use App\Traits\BelongsToUser;
use App\Traits\ShareableModel;
use App\Traits\TaggableModel;
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
    use ShareableModel;
    use TaggableModel;

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

    /**
     * Get the shareable type for FileShare records.
     */
    protected function getShareableType(): string
    {
        return 'receipt';
    }

    /**
     * Get the taggable type for the pivot table.
     */
    protected function getTaggableType(): string
    {
        return 'receipt';
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        $this->load(['merchant', 'lineItems.vendor']);

        $array = [
            // Ensure search engine can filter by user
            'user_id' => $this->user_id,
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
                    'vendor_id' => $item->vendor_id,
                    'vendor_name' => $item->vendor?->name,
                ];
            })->toArray(),
            'vendors' => $this->lineItems
                ->filter(fn($li) => !empty($li->vendor?->name))
                ->map(fn($li) => $li->vendor->name)
                ->unique()
                ->values()
                ->toArray(),
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
