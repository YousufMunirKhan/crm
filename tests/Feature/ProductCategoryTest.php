<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Product;
use App\Modules\CRM\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Categories were a free-text column on products: renaming one meant an UPDATE
 * across every row, and "ePOS" / "epos" / " ePOS " were three categories as far
 * as reporting was concerned.
 *
 * The string column is still written, because seven places still read it. These
 * tests pin the rule that matters during that overlap - the two must never
 * disagree.
 */
class ProductCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'Admin'], ['description' => 'Admin']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_the_fallback_category_exists_after_migrating(): void
    {
        $this->assertNotNull(ProductCategory::where('slug', 'uncategorized')->first());
    }

    public function test_writing_the_legacy_string_creates_and_links_the_category(): void
    {
        $product = Product::create(['name' => 'Card Machine', 'category' => 'Card Terminal']);

        $this->assertNotNull($product->category_id);
        $this->assertSame('Card Terminal', $product->productCategory->name);
        $this->assertSame('Card Terminal', $product->category);
    }

    public function test_setting_the_category_id_updates_the_string(): void
    {
        $category = ProductCategory::create(['name' => 'Business Funding']);
        $product = Product::create(['name' => 'Merchant Loan', 'category_id' => $category->id]);

        $this->assertSame('Business Funding', $product->fresh()->category);
    }

    /**
     * The whole point of the table: spelling variants land on one row instead of
     * quietly splitting a category in the reports.
     */
    public function test_case_and_spacing_variants_land_on_one_category(): void
    {
        Product::create(['name' => 'A', 'category' => 'ePOS Bundle']);
        Product::create(['name' => 'B', 'category' => 'epos bundle']);
        Product::create(['name' => 'C', 'category' => '  ePOS   Bundle ']);

        $this->assertSame(1, ProductCategory::where('slug', 'epos-bundle')->count());
        $this->assertSame(3, ProductCategory::where('slug', 'epos-bundle')->first()->products()->count());
    }

    public function test_renaming_a_category_is_one_edit(): void
    {
        $category = ProductCategory::create(['name' => 'Terminals']);
        Product::create(['name' => 'A', 'category_id' => $category->id]);
        Product::create(['name' => 'B', 'category_id' => $category->id]);

        $category->update(['name' => 'Card Terminals']);

        $this->assertSame(
            ['Card Terminals', 'Card Terminals'],
            $category->products()->get()->map(fn (Product $p) => $p->productCategory->name)->all(),
        );
    }

    public function test_a_product_created_without_a_category_falls_into_uncategorized(): void
    {
        $response = $this->actingAs($this->admin())
            ->postJson('/api/products', ['name' => 'From an invoice'])
            ->assertCreated();

        $this->assertSame('Uncategorized', $response->json('product_category.name'));
    }

    public function test_the_listing_can_be_filtered_by_category_id_and_by_name(): void
    {
        $terminals = ProductCategory::create(['name' => 'Card Terminal']);
        $wanted = Product::create(['name' => 'Reader', 'category_id' => $terminals->id, 'is_active' => true]);
        Product::create(['name' => 'Website', 'category' => 'Website', 'is_active' => true]);

        $admin = $this->admin();

        $byId = $this->actingAs($admin)
            ->getJson('/api/products?category_id='.$terminals->id)
            ->assertOk()->json('*.id');
        $this->assertSame([$wanted->id], $byId);

        // Callers that were passing the name must keep working.
        $byName = $this->actingAs($admin)
            ->getJson('/api/products?category=Card%20Terminal')
            ->assertOk()->json('*.id');
        $this->assertSame([$wanted->id], $byName);
    }

    public function test_the_categories_endpoint_lists_the_table_not_distinct_strings(): void
    {
        $empty = ProductCategory::create(['name' => 'Brand New', 'sort_order' => 1]);
        Product::create(['name' => 'A', 'category' => 'Website', 'is_active' => true]);

        $body = $this->actingAs($this->admin())->getJson('/api/products/categories')->assertOk()->json();

        // A category with no products still appears - the old SELECT DISTINCT
        // dropped it the moment its last product went inactive.
        $this->assertContains('Brand New', $body['data']);
        $this->assertContains($empty->id, array_column($body['categories'], 'id'));
    }

    public function test_a_category_coming_back_into_use_is_restored_not_duplicated(): void
    {
        $category = ProductCategory::create(['name' => 'Seasonal']);
        $category->delete();

        Product::create(['name' => 'Xmas bundle', 'category' => 'Seasonal']);

        $this->assertSame(1, ProductCategory::withTrashed()->where('slug', 'seasonal')->count());
        $this->assertFalse(ProductCategory::withTrashed()->where('slug', 'seasonal')->first()->trashed());
    }
}
