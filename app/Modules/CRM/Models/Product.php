<?php

namespace App\Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasAuditLog;

    protected $fillable = [
        'name',
        'description',
        'category',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function leadItems(): HasMany
    {
        return $this->hasMany(\App\Modules\CRM\Models\LeadItem::class);
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

