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
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $name = trim($data['name']);

        // If a product with the same name already exists, return it instead of creating a duplicate
        $existing = Product::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($name)])->first();
        if ($existing) {
            return response()->json($existing, 200);
        }

        // When creating from invoice/elsewhere without category, assign a default at random
        if (empty($data['category'])) {
            $defaultCategories = ['Uncategorized', 'General', 'Other', 'Misc'];
            $data['category'] = $defaultCategories[array_rand($defaultCategories)];
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
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $product->update($data);

        return response()->json($product);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

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

