<?php

namespace App\Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Models\Product;
use App\Modules\CRM\Models\ProductCategory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()->with('productCategory');

        // For API calls without active_only param, default to active products only
        if (!$request->has('active_only') || $request->active_only !== 'false') {
            $query->where('is_active', true);
        }

        // Accepts an id from the new picker, and still accepts a name so the
        // callers that were passing a category string keep working.
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        } elseif ($request->filled('category')) {
            $category = $request->category;
            $query->where(function ($q) use ($category) {
                $q->where('category', $category)
                    ->orWhereHas('productCategory', fn ($c) => $c->where('slug', Str::slug($category)));
            });
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('name')->get();

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:64', 'unique:products,sku'],
            'description' => ['nullable', 'string'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3'],
            'unit' => ['nullable', 'string', 'max:32'],
            'category' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $name = trim($data['name']);

        // If a product with the same name already exists, return it instead of creating a duplicate
        $existing = Product::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($name)])->first();
        if ($existing) {
            return response()->json($existing, 200);
        }

        // Products created from an invoice arrive without a category. Use one
        // stable bucket: a random pick scattered them across target categories
        // and corrupted target attainment reporting.
        if (empty($data['category']) && empty($data['category_id'])) {
            $data['category_id'] = ProductCategory::fallback()->id;
            unset($data['category']);
        }

        $product = Product::create($data);

        return response()->json($product->load('productCategory'), 201);
    }

    public function show($id)
    {
        $product = Product::with(['productCategory', 'suggestedProducts', 'suggestedByProducts'])->findOrFail($id);
        return response()->json($product);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:64', 'unique:products,sku,'.$id],
            'description' => ['nullable', 'string'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3'],
            'unit' => ['nullable', 'string', 'max:32'],
            'category' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $product->update($data);

        return response()->json($product->load('productCategory'));
    }

    /**
     * Retires a product. Soft delete, so line items on historic leads and
     * invoices keep resolving - a hard delete hit the lead_items foreign key
     * and returned a 500 for any product that had ever been sold.
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->update(['is_active' => false]);
        $product->delete();

        return response()->noContent();
    }

    /**
     * Cross-sell links for a product, in both directions.
     *
     * product_relationships, its model relations, the read API and the UI panel
     * all existed - but nothing anywhere could write a row, so the cross-sell
     * panel rendered empty in production. These are the missing write paths.
     */
    public function relationships($id)
    {
        $product = Product::with(['suggestedProducts', 'suggestedByProducts'])->findOrFail($id);

        return response()->json([
            'suggests' => $product->suggestedProducts->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'category' => $p->category,
                'relationship_type' => $p->pivot->relationship_type,
            ])->values(),
            'suggested_by' => $product->suggestedByProducts->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'relationship_type' => $p->pivot->relationship_type,
            ])->values(),
        ]);
    }

    public function storeRelationship(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'to_product_id' => ['required', 'integer', 'exists:products,id', 'different:'.$id],
            'relationship_type' => ['required', 'in:suggest,upsell,cross_sell'],
        ]);

        $product->suggestedProducts()->syncWithoutDetaching([
            $data['to_product_id'] => ['relationship_type' => $data['relationship_type']],
        ]);

        // syncWithoutDetaching does not update the pivot of an existing row.
        $product->suggestedProducts()->updateExistingPivot(
            $data['to_product_id'],
            ['relationship_type' => $data['relationship_type']]
        );

        return response()->json(['message' => 'Relationship saved.'], 201);
    }

    public function destroyRelationship($id, $toProductId)
    {
        $product = Product::findOrFail($id);
        $product->suggestedProducts()->detach((int) $toProductId);

        return response()->noContent();
    }

    public function getSuggestedProducts($id)
    {
        $product = Product::findOrFail($id);
        $suggested = $product->getSuggestedProducts();
        return response()->json($suggested);
    }

    /**
     * Creates a category from the products screen.
     *
     * Deliberately not silent about duplicates. The whole reason categories
     * moved out of a free-text column is that "ePOS" and "epos" became two
     * categories and split the reporting, so a name that resolves to an
     * existing category returns that category with a note rather than making
     * a second one - and the caller can tell the two cases apart by `created`.
     */
    public function storeCategory(Request $request)
    {
        Gate::authorize('create', ProductCategory::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $name = trim(preg_replace('/\s+/u', ' ', $data['name']));
        $slug = Str::slug($name);

        if ($slug === '') {
            return response()->json([
                'message' => 'That name has no letters or numbers in it.',
            ], 422);
        }

        $existing = ProductCategory::withTrashed()->where('slug', $slug)->first();

        if ($existing && ! $existing->trashed()) {
            return response()->json([
                'created' => false,
                'message' => "\"{$existing->name}\" already exists.",
                'category' => $existing->only(['id', 'name', 'slug']),
            ], 200);
        }

        $category = ProductCategory::findOrCreateByName($name);

        if ($category && ! empty($data['description'])) {
            $category->update(['description' => $data['description']]);
        }

        return response()->json([
            'created' => true,
            'message' => "\"{$category->name}\" added.",
            'category' => $category->only(['id', 'name', 'slug']),
        ], 201);
    }

    /**
     * Product categories for pickers and filters.
     *
     * This used to be SELECT DISTINCT over the free-text column, so a category
     * vanished from the list the moment its last product was deactivated, and
     * a typo appeared in it as if it were real. It reads the table now.
     *
     * `data` stays a plain array of names because the target pickers and the
     * Filament datalist consume it that way; the richer shape is under
     * `categories` for callers that want ids and counts.
     */
    public function categories()
    {
        $categories = ProductCategory::query()
            ->active()
            ->ordered()
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->get();

        return response()->json([
            'data' => $categories->pluck('name')->values(),
            'categories' => $categories->map(fn (ProductCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'products_count' => $c->products_count,
            ])->values(),
        ]);
    }
}

