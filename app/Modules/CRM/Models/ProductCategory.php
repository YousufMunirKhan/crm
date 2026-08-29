<?php

namespace App\Modules\CRM\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A product category.
 *
 * Categories used to be a free-text string on each product, so renaming one
 * meant an UPDATE across every row and a single typo created a new category
 * nobody intended.
 */
class ProductCategory extends Model
{
    use HasAuditLog, SoftDeletes;

    /** The category products fall into when none is chosen. */
    public const FALLBACK = 'Uncategorized';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        // The slug is the match key used when a legacy write sends only the
        // category name, so it is derived rather than accepted from input.
        static::saving(function (self $category) {
            if ($category->slug === null || $category->isDirty('name')) {
                $category->slug = static::uniqueSlug($category->name, $category->id);
            }
        });
    }

    protected static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $n = 2;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $q) => $q->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Sort order first, then name - alphabetical alone is rarely the order a
     * salesperson wants to scan.
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Finds a category by name, creating it if it is genuinely new.
     *
     * Matching is on the slug, so "ePOS Bundle", "epos bundle" and
     * " ePOS  Bundle " all land on the same row instead of quietly becoming
     * three categories the way the free-text column allowed.
     */
    public static function findOrCreateByName(?string $name): ?self
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $name));

        if ($name === '') {
            return null;
        }

        $slug = Str::slug($name);

        if ($slug === '') {
            return null;
        }

        $existing = static::withTrashed()->where('slug', $slug)->first();

        if ($existing) {
            // A category coming back into use should not stay soft-deleted.
            if ($existing->trashed()) {
                $existing->restore();
            }

            return $existing;
        }

        return static::create([
            'name' => $name,
            'sort_order' => (int) static::max('sort_order') + 10,
            'is_active' => true,
        ]);
    }

    public static function fallback(): self
    {
        return static::findOrCreateByName(self::FALLBACK);
    }
}
