<?php

namespace App\Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasAuditLog, SoftDeletes;

    protected $fillable = [
        'name',
        'sku',
        'description',
        'unit_price',
        'cost_price',
        'tax_rate',
        'currency',
        'unit',
        'image_path',
        'category',
        'category_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
    ];

    /**
     * Cost price is commercially sensitive - only managers see margin.
     */
    protected $hidden = ['cost_price'];

    /**
     * Keeps the free-text `category` column and the `category_id` relation
     * agreeing with each other.
     *
     * Both write paths are still live: the Filament form and the products
     * screen send a category name, while newer code sets category_id. Rather
     * than change all seven readers at once on a running system - and miss one
     * - whichever field was written wins and the other is derived from it. The
     * string can be dropped once nothing reads it.
     */
    protected static function booted(): void
    {
        static::saving(function (self $product) {
            if ($product->isDirty('category_id')) {
                $product->category = $product->category_id
                    ? ProductCategory::find($product->category_id)?->name
                    : null;

                return;
            }

            if ($product->isDirty('category')) {
                $product->category_id = ProductCategory::findOrCreateByName($product->category)?->id;
            }
        });
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function leadItems(): HasMany
    {
        return $this->hasMany(\App\Modules\CRM\Models\LeadItem::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(\App\Modules\Invoice\Models\InvoiceItem::class);
    }

    /**
     * Margin per unit, or null when either price is unknown.
     */
    public function marginAttribute(): ?float
    {
        if ($this->unit_price === null || $this->cost_price === null) {
            return null;
        }

        return round((float) $this->unit_price - (float) $this->cost_price, 2);
    }

    public function suggestedProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_relationships',
            'from_product_id',
            'to_product_id'
        )->withPivot('relationship_type')
          ->withTimestamps();
    }

    public function suggestedByProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_relationships',
            'to_product_id',
            'from_product_id'
        )->withPivot('relationship_type')
          ->withTimestamps();
    }

    /**
     * Get suggested products based on relationships
     */
    public function getSuggestedProducts()
    {
        return $this->suggestedProducts()->where('is_active', true)->get();
    }
}

