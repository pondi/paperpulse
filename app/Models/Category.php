<?php

namespace App\Models;

use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'color',
        'icon',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the user that owns the category.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the receipts for the category.
     */
    public function receipts()
    {
        return $this->hasMany(Receipt::class);
    }

    /**
     * Get the documents for the category.
     */
    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Scope a query to only include active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to order by sort order and name.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Generate a unique slug for the category.
     */
    public static function generateUniqueSlug($name, $userId, $excludeId = null)
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        while (true) {
            $query = static::where('user_id', $userId)->where('slug', $slug);

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            if (! $query->exists()) {
                break;
            }

            $slug = $originalSlug.'-'.$count;
            $count++;
        }

        return $slug;
    }

    /**
     * Get the total amount for all receipts in this category.
     */
    public function getTotalAmountAttribute()
    {
        return $this->receipts()->sum('total_amount');
    }

    /**
     * Get the receipt count for this category.
     */
    public function getReceiptCountAttribute()
    {
        return $this->receipts()->count();
    }

    /**
     * Get the document count for this category.
     */
    public function getDocumentCountAttribute()
    {
        return $this->documents()->count();
    }

    /**
     * Get the total item count (receipts + documents) for this category.
     */
    public function getTotalItemCountAttribute()
    {
        return $this->receipts()->count() + $this->documents()->count();
    }

    /**
     * Default categories that can be created for new users
     */
    public static function getDefaultCategories()
    {
        return [
            ['name' => 'Food & Dining', 'slug' => 'food-dining', 'color' => '#10B981', 'icon' => 'utensils'],
            ['name' => 'Transport', 'slug' => 'transport', 'color' => '#3B82F6', 'icon' => 'car'],
            ['name' => 'Shopping', 'slug' => 'shopping', 'color' => '#F59E0B', 'icon' => 'shopping-bag'],
            ['name' => 'Entertainment', 'slug' => 'entertainment', 'color' => '#EF4444', 'icon' => 'film'],
            ['name' => 'Utilities', 'slug' => 'utilities', 'color' => '#6366F1', 'icon' => 'bolt'],
            ['name' => 'Healthcare', 'slug' => 'healthcare', 'color' => '#EC4899', 'icon' => 'heart'],
            ['name' => 'Home & Garden', 'slug' => 'home-garden', 'color' => '#84CC16', 'icon' => 'home'],
            ['name' => 'Other', 'slug' => 'other', 'color' => '#6B7280', 'icon' => 'dots-horizontal'],
        ];
    }
}
