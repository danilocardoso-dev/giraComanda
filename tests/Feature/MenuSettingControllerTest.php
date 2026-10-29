<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\ProductPhoto;
use App\Models\User;
use App\ProductSaleType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MenuSettingControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_admin_manages_menu_settings(): void
    {
        $attendant = User::factory()->create();

        $this->actingAs($attendant)->get(route('menu-settings.edit'))->assertForbidden();
        $this->post(route('menu-categories.store'), ['name' => 'Lanches'])->assertForbidden();
    }

    public function test_admin_toggles_ordering_for_current_branch(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('menu-settings.update'), ['menu_ordering_enabled' => '1'])
            ->assertRedirect(route('menu-settings.edit'));
        $this->assertTrue(Branch::query()->where('slug', 'matriz')->value('menu_ordering_enabled'));

        $this->put(route('menu-settings.update'), ['menu_ordering_enabled' => '0']);
        $this->assertFalse(Branch::query()->where('slug', 'matriz')->value('menu_ordering_enabled'));
    }

    public function test_admin_creates_updates_and_removes_categories(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('menu-categories.store'), ['name' => ' Lanches ', 'position' => '2'])
            ->assertRedirect(route('menu-settings.edit'));
        $category = MenuCategory::query()->sole();
        $this->assertSame('Lanches', $category->name);
        $this->assertSame(2, $category->position);

        $this->post(route('menu-categories.store'), ['name' => 'Lanches'])
            ->assertSessionHasErrors(['name' => 'Já existe uma categoria com este nome.']);

        $this->put(route('menu-categories.update', $category), ['name' => 'Sanduíches', 'position' => '1']);
        $this->assertSame('Sanduíches', $category->refresh()->name);

        $product = Product::factory()->create(['menu_category_id' => $category->id]);
        $this->delete(route('menu-categories.destroy', $category))->assertRedirect(route('menu-settings.edit'));

        $this->assertDatabaseCount('menu_categories', 0);
        $this->assertNull($product->refresh()->menu_category_id);
    }

    public function test_admin_publishes_product_on_menu_with_photo_and_can_remove_it(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $category = MenuCategory::factory()->create();

        $this->actingAs($admin)->post(route('products.store'), [
            'name' => 'X-Salada',
            'sale_type' => ProductSaleType::Unit->value,
            'price' => '24,90',
            'active' => '1',
            'show_on_menu' => '1',
            'menu_category_id' => $category->id,
            'description' => 'Pão, carne, queijo e salada.',
            'photo' => UploadedFile::fake()->image('x-salada.jpg'),
        ])->assertRedirect(route('products.index'));

        $product = Product::query()->sole();
        $this->assertTrue($product->show_on_menu);
        $this->assertSame($category->id, $product->menu_category_id);
        $this->assertSame('Pão, carne, queijo e salada.', $product->description);
        Storage::disk('public')->assertExists($product->photo_path);
        $this->get(route('products.photo', $product))->assertOk();

        $photoPath = $product->photo_path;
        $this->put(route('products.update', $product), [
            'name' => 'X-Salada',
            'sale_type' => ProductSaleType::Unit->value,
            'price' => '24,90',
            'active' => '1',
            'show_on_menu' => '1',
            'remove_photo' => '1',
        ])->assertRedirect(route('products.index'));

        $this->assertNull($product->refresh()->photo_path);
        Storage::disk('public')->assertMissing($photoPath);
    }

    public function test_admin_adds_and_removes_gallery_photos_within_limit(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['name' => 'X-Tudo', 'show_on_menu' => true]);
        $attributes = ['name' => 'X-Tudo', 'sale_type' => ProductSaleType::Unit->value, 'price' => '30,00', 'active' => '1', 'show_on_menu' => '1'];

        $this->actingAs($admin)->put(route('products.update', $product), [
            ...$attributes,
            'gallery_photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])->assertRedirect(route('products.index'));

        $photos = $product->photos()->get();
        $this->assertSame([1, 2], $photos->pluck('position')->all());
        Storage::disk('public')->assertExists($photos->pluck('path')->all());
        $this->get(route('products.photo', ['product' => $product, 'photo' => $photos->first()]))->assertOk();

        $this->put(route('products.update', $product), [
            ...$attributes,
            'gallery_photos' => array_map(fn (int $index) => UploadedFile::fake()->image("extra-{$index}.jpg"), range(1, 4)),
        ])->assertSessionHasErrors(['gallery_photos' => 'O produto pode ter no máximo 5 fotos extras. Remova alguma antes de enviar novas.']);
        $this->assertSame(2, $product->photos()->count());

        $this->put(route('products.update', $product), [
            ...$attributes,
            'remove_gallery_photos' => [$photos->first()->id],
        ])->assertRedirect(route('products.index'));

        $this->assertSame([$photos->last()->id], $product->photos()->pluck('id')->all());
        Storage::disk('public')->assertMissing($photos->first()->path);
    }

    public function test_gallery_photo_must_belong_to_the_product_in_url(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $otherProductPhoto = ProductPhoto::factory()->create();

        $this->actingAs($admin)
            ->get(route('products.photo', ['product' => $product, 'photo' => $otherProductPhoto]))
            ->assertNotFound();
    }

    public function test_product_rejects_category_from_another_company(): void
    {
        $admin = User::factory()->admin()->create();
        $foreignCategory = MenuCategory::factory()->create(['company_id' => Company::factory()->create()->id]);

        $this->actingAs($admin)->post(route('products.store'), [
            'name' => 'Item',
            'sale_type' => ProductSaleType::Unit->value,
            'price' => '10,00',
            'active' => '1',
            'menu_category_id' => $foreignCategory->id,
        ])->assertSessionHasErrors(['menu_category_id' => 'A categoria selecionada é inválida.']);
    }
}
