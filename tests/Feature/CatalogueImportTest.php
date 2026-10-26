<?php

namespace Tests\Feature;

use App\Http\Middleware\ShareWorkspace;
use App\Models\CatalogueProduct;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CatalogueImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CatalogueImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => [
            'database/migrations/2026_09_23_000001_create_salesdock_schema.php',
            'database/migrations/2026_10_08_000002_add_profile_completed_at_to_tenants.php',
            'database/migrations/2026_10_08_125551_add_deleted_at_to_tenants.php',
            'database/migrations/2026_10_25_110454_add_sku_to_catalogue_products_table.php',
        ], '--no-interaction' => true])->assertExitCode(0);
    }

    public function test_guests_cannot_upload_catalogue(): void
    {
        $this->post(route('admin.catalogue.import'))->assertRedirect('/login');
    }

    public function test_admin_upload_preserves_sku_and_repeat_upload_skips_duplicates(): void
    {
        $user = new User(['id' => 'admin', 'isSuperAdmin' => true]);
        $user->setRelation('role', null);
        $user->setRelation('tenant', null);
        $this->actingAs($user);
        $csv = "name,sku,category\n\"Beans, white\",SD-001,\"Grains, Legumes & Seeds > Bulk Bags\"\n";

        $this->post(route('admin.catalogue.import'), ['file' => UploadedFile::fake()->createWithContent('products.csv', $csv)])
            ->assertRedirect(route('admin.catalogue'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('catalogue_products', ['name' => 'Beans, white', 'sku' => 'SD-001', 'category' => 'Grains, Legumes & Seeds > Bulk Bags']);

        $this->post(route('admin.catalogue.import'), ['file' => UploadedFile::fake()->createWithContent('products.csv', $csv)])
            ->assertRedirect(route('admin.catalogue'))
            ->assertSessionHas('status', '0 catalogue items imported from CSV. 1 existing or duplicate rows skipped.');

        $this->assertDatabaseCount('catalogue_products', 1);
    }

    public function test_bulk_import_skips_duplicates_across_chunks(): void
    {
        $rows = [];
        for ($index = 1; $index <= 7547; $index++) {
            $rows[] = ['name' => 'Product '.$index, 'category' => 'Food', 'sku' => 'SD-'.$index];
        }
        $rows[] = ['name' => 'PRODUCT 1', 'category' => 'food', 'sku' => null];
        $rows[] = ['name' => 'Different name', 'category' => 'Food', 'sku' => 'SD-2'];

        DB::enableQueryLog();
        $result = app(CatalogueImport::class)->import($rows, 'admin');
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(['saved' => 7547, 'skipped' => 2, 'skuAdded' => 0], $result);
        $this->assertLessThan(50, $queryCount);
        $this->assertDatabaseCount('catalogue_products', 7547);
        $this->assertDatabaseHas('catalogue_products', ['name' => 'Product 7547', 'sku' => 'SD-7547']);
    }

    public function test_existing_products_only_gain_missing_sku_without_overwriting_details(): void
    {
        $item = CatalogueProduct::query()->create(['name' => 'Milk', 'category' => 'Beverages', 'createdBy' => 'admin', 'brand' => 'Original']);

        $result = app(CatalogueImport::class)->import([['name' => 'Milk', 'category' => 'Beverages', 'sku' => 'SD-001', 'brand' => 'Changed']], 'admin');

        $this->assertSame(['saved' => 0, 'skipped' => 1, 'skuAdded' => 1], $result);
        $this->assertSame('SD-001', $item->fresh()->sku);
        $this->assertSame('Original', $item->fresh()->brand);
    }

    public function test_incomplete_upload_is_rejected_before_any_products_are_added(): void
    {
        $user = new User(['id' => 'admin', 'isSuperAdmin' => true]);
        $user->setRelation('role', null);
        $user->setRelation('tenant', null);

        $this->actingAs($user)->post(route('admin.catalogue.import'), [
            'file' => UploadedFile::fake()->createWithContent('products.csv', "name,category\nMilk,Beverages\nBread,\n"),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('catalogue_products', 0);
    }

    public function test_non_admin_cannot_upload_shared_catalogue(): void
    {
        $user = new User(['id' => 'staff', 'isSuperAdmin' => false]);
        $user->setRelation('role', null);
        $user->setRelation('tenant', null);

        $this->actingAs($user)->post(route('admin.catalogue.import'))->assertRedirect(route('unauthorized'));

        $this->assertDatabaseCount('catalogue_products', 0);
    }

    public function test_catalogue_sku_is_preserved_and_can_be_used_by_different_shops(): void
    {
        $item = CatalogueProduct::query()->create(['name' => 'Milk', 'category' => 'Beverages', 'sku' => 'SD-001', 'createdBy' => 'admin']);
        $first = $this->shopOwner('first');
        $second = $this->shopOwner('second');
        $this->withoutMiddleware(ShareWorkspace::class);

        $this->actingAs($first)->post(route('products.catalogue'), ['catalogueId' => $item->id, 'price' => 1500, 'currentStock' => 0])
            ->assertRedirect(route('products'))->assertSessionHasNoErrors();
        $this->actingAs($second)->post(route('products.catalogue'), ['catalogueId' => $item->id, 'price' => 2000, 'currentStock' => 0])
            ->assertRedirect(route('products'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', ['tenantId' => $first->tenantId, 'sku' => 'SD-001', 'price' => 1500]);
        $this->assertDatabaseHas('products', ['tenantId' => $second->tenantId, 'sku' => 'SD-001', 'price' => 2000]);
    }

    public function test_starter_import_skips_sku_conflicts_and_continues_with_other_items(): void
    {
        $owner = $this->shopOwner('shop');
        Product::query()->create(['tenantId' => $owner->tenantId, 'name' => 'Existing milk', 'sku' => 'SD-001', 'price' => 1200]);
        $conflict = CatalogueProduct::query()->create(['name' => 'Milk', 'category' => 'Beverages', 'sku' => 'SD-001', 'createdBy' => 'admin']);
        $fresh = CatalogueProduct::query()->create(['name' => 'Tea', 'category' => 'Beverages', 'sku' => 'SD-002', 'createdBy' => 'admin']);
        $legacy = CatalogueProduct::query()->create(['name' => 'Old product', 'category' => 'Food', 'createdBy' => 'admin']);

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->post(route('products.starter.import'), ['items' => [
                ['id' => $conflict->id, 'price' => 1500],
                ['id' => $fresh->id, 'price' => 2500],
                ['id' => $legacy->id, 'price' => 3000],
            ]])->assertRedirect(route('products'))
            ->assertSessionHas('status', '2 products imported. 1 rows were skipped.');

        $this->assertDatabaseHas('products', ['tenantId' => $owner->tenantId, 'name' => 'Existing milk', 'sku' => 'SD-001', 'price' => 1200]);
        $this->assertDatabaseHas('products', ['tenantId' => $owner->tenantId, 'name' => 'Tea', 'sku' => 'SD-002']);
        $this->assertNotEmpty(Product::query()->where('name', 'Old product')->value('sku'));
        $this->assertDatabaseCount('products', 3);
    }

    public function test_suggestions_match_detailed_categories_and_unmatched_shops_can_browse_all(): void
    {
        $owner = $this->shopOwner('shop');
        DB::table('tenant_registrations')->insert([
            'id' => 'registration', 'businessName' => 'Shop', 'ownerName' => 'Owner', 'email' => $owner->email,
            'passwordHash' => 'unused', 'businessType' => 'Fashion', 'updatedAt' => now(),
        ]);
        CatalogueProduct::query()->create(['name' => 'Trousers', 'category' => "Men's Clothing > Trousers", 'createdBy' => 'admin']);
        CatalogueProduct::query()->create(['name' => 'Milk', 'category' => 'Beverages > Milk', 'createdBy' => 'admin']);

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)->get(route('products.starter'))
            ->assertOk()->assertViewHas('items', fn ($items): bool => $items->pluck('name')->all() === ['Trousers']);

        $this->get(route('products.starter', ['category' => 'Beverages > Milk']))
            ->assertOk()
            ->assertViewHas('pickedCategory', 'Beverages > Milk')
            ->assertViewHas('items', fn ($items): bool => $items->pluck('name')->all() === ['Milk']);

        DB::table('tenant_registrations')->where('id', 'registration')->update(['businessType' => 'General']);

        $this->get(route('products.starter'))->assertOk()->assertViewHas('items', fn ($items): bool => $items->count() === 2);
    }

    private function shopOwner(string $slug): User
    {
        $tenant = Tenant::query()->create(['name' => $slug, 'slug' => $slug]);
        $role = Role::query()->create(['tenantId' => $tenant->id, 'name' => 'Owner', 'role' => 'ADMIN', 'permissions' => []]);

        return User::query()->create(['tenantId' => $tenant->id, 'roleId' => $role->id, 'name' => 'Owner', 'email' => $slug.'@example.test']);
    }

    public function test_partial_selection_ignores_unchecked_rows_even_when_they_include_numbers(): void
    {
        $owner = $this->shopOwner('partial');
        $first = CatalogueProduct::query()->create(['name' => 'Milk', 'category' => 'Beverages', 'createdBy' => 'admin']);
        $second = CatalogueProduct::query()->create(['name' => 'Tea', 'category' => 'Beverages', 'createdBy' => 'admin']);

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->post(route('products.starter.import'), ['items' => [
                1 => ['id' => $first->id, 'price' => 1500],
                2 => ['price' => '', 'stock' => ''],
                5 => ['id' => $second->id, 'price' => 2500],
                6 => ['price' => 123, 'stock' => 0],
            ]])->assertRedirect(route('products'))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseHas('products', ['tenantId' => $owner->tenantId, 'name' => 'Milk', 'price' => 1500]);
        $this->assertDatabaseHas('products', ['tenantId' => $owner->tenantId, 'name' => 'Tea', 'price' => 2500]);
    }

    public function test_selected_product_still_requires_a_valid_price(): void
    {
        $owner = $this->shopOwner('invalid-price');
        $item = CatalogueProduct::query()->create(['name' => 'Milk', 'category' => 'Beverages', 'createdBy' => 'admin']);

        $this->withoutMiddleware(ShareWorkspace::class)->actingAs($owner)
            ->post(route('products.starter.import'), ['items' => [5 => ['id' => $item->id, 'price' => '']]])
            ->assertSessionHasErrors('items.5.price');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_admin_can_open_a_category_and_see_editable_products(): void
    {
        $admin = new User(['id' => 'admin', 'isSuperAdmin' => true, 'name' => 'Admin']);
        $admin->setRelation('role', null);
        $admin->setRelation('tenant', null);
        $item = CatalogueProduct::query()->create(['name' => 'Milk', 'sku' => 'SD-001', 'category' => 'Beverages', 'createdBy' => 'admin']);
        CatalogueProduct::query()->create(['name' => 'Bread', 'category' => 'Food', 'createdBy' => 'admin']);

        $this->actingAs($admin)->get(route('admin.catalogue', ['category' => 'Beverages']))
            ->assertOk()
            ->assertViewHas('items', fn ($items): bool => $items->pluck('id')->all() === [$item->id])
            ->assertSee('SD-001')
            ->assertSee('data-sku="SD-001"', false)
            ->assertSee('name="sku"', false);
    }

    public function test_admin_sku_conflict_is_rejected_without_changing_product_details(): void
    {
        $admin = new User(['id' => 'admin', 'isSuperAdmin' => true]);
        $admin->setRelation('role', null);
        $admin->setRelation('tenant', null);
        $item = CatalogueProduct::query()->create(['name' => 'Milk', 'sku' => 'SD-001', 'category' => 'Beverages', 'createdBy' => 'admin']);
        CatalogueProduct::query()->create(['name' => 'Tea', 'sku' => 'SD-002', 'category' => 'Beverages', 'createdBy' => 'admin']);

        $this->actingAs($admin)->put(route('admin.catalogue.update', $item), [
            'name' => 'Changed milk', 'category' => 'Beverages', 'sku' => 'SD-002',
        ])->assertSessionHasErrors('sku');

        $this->assertSame('Milk', $item->fresh()->name);
        $this->assertSame('SD-001', $item->fresh()->sku);
    }

    public function test_admin_can_edit_catalogue_details_without_changing_existing_shop_products(): void
    {
        $owner = $this->shopOwner('existing-shop');
        $product = Product::query()->create(['tenantId' => $owner->tenantId, 'name' => 'Milk', 'sku' => 'SD-001', 'price' => 1500]);
        $item = CatalogueProduct::query()->create(['name' => 'Milk', 'sku' => 'SD-001', 'category' => 'Beverages', 'createdBy' => 'admin']);
        $admin = new User(['id' => 'admin', 'isSuperAdmin' => true]);
        $admin->setRelation('role', null);
        $admin->setRelation('tenant', null);
        $originalStorage = $this->app->storagePath();
        $testStorage = $originalStorage.'/framework/testing/catalogue-edit';
        File::ensureDirectoryExists($testStorage.'/app');
        $this->app->useStoragePath($testStorage);

        try {
            $this->actingAs($admin)->put(route('admin.catalogue.update', $item), [
                'name' => 'Whole milk', 'sku' => 'SD-001', 'category' => 'Beverages',
                'brand' => 'Example', 'unit' => 'tin', 'barcode' => '001234',
                'description' => 'Updated description', 'isActive' => 1,
            ])->assertSessionHasNoErrors();
        } finally {
            $this->app->useStoragePath($originalStorage);
            File::deleteDirectory($testStorage);
        }

        $this->assertDatabaseHas('catalogue_products', [
            'id' => $item->id, 'name' => 'Whole milk', 'sku' => 'SD-001', 'brand' => 'Example',
            'unit' => 'tin', 'barcode' => '001234', 'description' => 'Updated description',
        ]);
        $this->assertSame('Milk', $product->fresh()->name);
        $this->assertSame('SD-001', $product->fresh()->sku);
        $this->assertEquals(1500, $product->fresh()->price);
    }
}
