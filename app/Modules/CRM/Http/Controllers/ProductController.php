<?php

namespace App\Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();

        // For API calls without active_only param, default to active products only
        if (!$request->has('active_only') || $request->active_only !== 'false') {
            $query->where('is_active', true);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
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
        if (empty($data['category'])) {
            $data['category'] = 'Uncategorized';
        }

        $product = Product::create($data);

        return response()->json($product, 201);
    }

    public function show($id)
    {
        $product = Product::with(['suggestedProducts', 'suggestedByProducts'])->findOrFail($id);
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
            'is_active' => ['nullable', 'boolean'],
        ]);

        $product->update($data);

        return response()->json($product);
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
     * Distinct product categories for target pickers (active products only).
     */
    public function categories()
    {
        $cats = Product::query()
            ->where('is_active', true)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->orderBy('category')
            ->pluck('category')
            ->map(fn ($c) => trim((string) $c))
            ->unique()
            ->values();

        return response()->json(['data' => $cats]);
    }
}

