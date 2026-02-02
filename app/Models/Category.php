<?php

namespace App\Models;

use App\Casts\PostgresBoolean;
use App\Enums\DeletedReason;
use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Category extends Model
{
    use BelongsToUser;
    use HasFactory;
    use SoftDeletes;

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
        'is_active' => PostgresBoolean::class,
        'sort_order' => 'integer',
        'deleted_reason' => DeletedReason::class,
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
        return $query->whereRaw('is_active = true');
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
            // Food & Beverages
            ['name' => 'Groceries', 'slug' => 'groceries', 'color' => '#10B981', 'icon' => 'shopping-cart'],
            ['name' => 'Restaurants & Dining', 'slug' => 'restaurants-dining', 'color' => '#059669', 'icon' => 'utensils'],
            ['name' => 'Coffee & Bakery', 'slug' => 'coffee-bakery', 'color' => '#065F46', 'icon' => 'coffee'],

            // Transportation
            ['name' => 'Fuel & Gas', 'slug' => 'fuel-gas', 'color' => '#3B82F6', 'icon' => 'gas-pump'],
            ['name' => 'Public Transport', 'slug' => 'public-transport', 'color' => '#2563EB', 'icon' => 'bus'],
            ['name' => 'Parking & Tolls', 'slug' => 'parking-tolls', 'color' => '#1E40AF', 'icon' => 'parking'],
            ['name' => 'Vehicle Maintenance', 'slug' => 'vehicle-maintenance', 'color' => '#1E3A8A', 'icon' => 'wrench'],

            // Shopping
            ['name' => 'Clothing & Accessories', 'slug' => 'clothing-accessories', 'color' => '#8B5CF6', 'icon' => 'shirt'],
            ['name' => 'Electronics', 'slug' => 'electronics', 'color' => '#6366F1', 'icon' => 'laptop'],
            ['name' => 'Books & Media', 'slug' => 'books-media', 'color' => '#4F46E5', 'icon' => 'book'],
            ['name' => 'Sports & Outdoors', 'slug' => 'sports-outdoors', 'color' => '#7C3AED', 'icon' => 'basketball'],
            ['name' => 'Personal Care & Beauty', 'slug' => 'personal-care-beauty', 'color' => '#EC4899', 'icon' => 'sparkles'],

            // Home & Living
            ['name' => 'Furniture', 'slug' => 'furniture', 'color' => '#84CC16', 'icon' => 'couch'],
            ['name' => 'Home Improvement', 'slug' => 'home-improvement', 'color' => '#65A30D', 'icon' => 'hammer'],
            ['name' => 'Garden & Plants', 'slug' => 'garden-plants', 'color' => '#16A34A', 'icon' => 'flower'],
            ['name' => 'Appliances', 'slug' => 'appliances', 'color' => '#14B8A6', 'icon' => 'refrigerator'],
            ['name' => 'Home Decor', 'slug' => 'home-decor', 'color' => '#0D9488', 'icon' => 'palette'],

            // Utilities & Bills
            ['name' => 'Electricity & Water', 'slug' => 'electricity-water', 'color' => '#F59E0B', 'icon' => 'bolt'],
            ['name' => 'Internet & Phone', 'slug' => 'internet-phone', 'color' => '#D97706', 'icon' => 'phone'],
            ['name' => 'Streaming Services', 'slug' => 'streaming-services', 'color' => '#B45309', 'icon' => 'tv'],
            ['name' => 'Insurance', 'slug' => 'insurance', 'color' => '#92400E', 'icon' => 'shield'],

            // Healthcare & Wellness
            ['name' => 'Pharmacy & Medicine', 'slug' => 'pharmacy-medicine', 'color' => '#EF4444', 'icon' => 'pill'],
            ['name' => 'Doctor & Medical', 'slug' => 'doctor-medical', 'color' => '#DC2626', 'icon' => 'stethoscope'],
            ['name' => 'Fitness & Gym', 'slug' => 'fitness-gym', 'color' => '#B91C1C', 'icon' => 'dumbbell'],
            ['name' => 'Dental & Vision', 'slug' => 'dental-vision', 'color' => '#991B1B', 'icon' => 'eye'],

            // Entertainment & Leisure
            ['name' => 'Movies & Events', 'slug' => 'movies-events', 'color' => '#F97316', 'icon' => 'ticket'],
            ['name' => 'Hobbies & Crafts', 'slug' => 'hobbies-crafts', 'color' => '#EA580C', 'icon' => 'palette'],
            ['name' => 'Travel & Hotels', 'slug' => 'travel-hotels', 'color' => '#C2410C', 'icon' => 'plane'],
            ['name' => 'Gaming', 'slug' => 'gaming', 'color' => '#9A3412', 'icon' => 'gamepad'],

            // Education & Work
            ['name' => 'Education & Courses', 'slug' => 'education-courses', 'color' => '#06B6D4', 'icon' => 'graduation-cap'],
            ['name' => 'Office Supplies', 'slug' => 'office-supplies', 'color' => '#0891B2', 'icon' => 'briefcase'],
            ['name' => 'Professional Services', 'slug' => 'professional-services', 'color' => '#0E7490', 'icon' => 'user-tie'],

            // Pets & Children
            ['name' => 'Pet Care & Supplies', 'slug' => 'pet-care-supplies', 'color' => '#A855F7', 'icon' => 'paw'],
            ['name' => 'Children & Toys', 'slug' => 'children-toys', 'color' => '#9333EA', 'icon' => 'baby'],
            ['name' => 'Baby Products', 'slug' => 'baby-products', 'color' => '#7E22CE', 'icon' => 'baby-carriage'],

            // Financial & Legal
            ['name' => 'Banking & Fees', 'slug' => 'banking-fees', 'color' => '#64748B', 'icon' => 'bank'],
            ['name' => 'Taxes & Legal', 'slug' => 'taxes-legal', 'color' => '#475569', 'icon' => 'scale'],
            ['name' => 'Donations & Charity', 'slug' => 'donations-charity', 'color' => '#334155', 'icon' => 'heart-hand'],

            // Miscellaneous
            ['name' => 'Gifts', 'slug' => 'gifts', 'color' => '#EC4899', 'icon' => 'gift'],
            ['name' => 'Other', 'slug' => 'other', 'color' => '#6B7280', 'icon' => 'dots-horizontal'],
        ];
    }
}
