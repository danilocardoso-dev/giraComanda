<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\ProductSaleType;
use App\StockMovementType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_attendant_cannot_manage_products(): void
    {
        $attendant = User::factory()->create();

        $response = $this->actingAs($attendant)->get(route('products.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_create_weight_product_with_stock(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('products.store'), [
            'name' => 'Comida por quilo',
            'sale_type' => ProductSaleType::Weight->value,
            'price' => '49,90',
            'stock' => '25.500',
            'active' => '1',
        ]);

        $product = Product::query()->sole();

        $response->assertRedirect(route('products.index'));
        $this->assertSame(4990, $product->price_cents);
        $this->assertSame(25500, $product->stock_milliunits);
        $this->assertSame(ProductSaleType::Weight, $product->sale_type);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'recorded_by' => $admin->id,
            'type' => StockMovementType::Initial->value,
            'quantity_delta_milliunits' => 25500,
            'balance_after_milliunits' => 25500,
        ]);
    }

    public function test_admin_is_redirected_to_product_list_after_updating_masked_price(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($admin)->put(route('products.update', $product), [
            'name' => 'Suco natural',
            'sale_type' => ProductSaleType::Unit->value,
            'price' => '1.234,56',
            'stock' => '10',
            'active' => '1',
        ]);

        $response->assertRedirect(route('products.index'));
        $this->assertSame(123456, $product->refresh()->price_cents);
    }

    public function test_fractional_stock_is_rejected_for_unit_product(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('products.store'), [
            'name' => 'Refrigerante',
            'sale_type' => ProductSaleType::Unit->value,
            'price' => '10.00',
            'stock' => '2.5',
            'active' => '1',
        ]);

        $response->assertSessionHasErrors(['stock' => 'Produtos por unidade exigem um estoque inteiro.']);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_product_list_highlights_low_stock(): void
    {
        $admin = User::factory()->admin()->create();
        Product::factory()->create([
            'name' => 'Refrigerante baixo',
            'stock_milliunits' => 2000,
            'min_stock_milliunits' => 3000,
        ]);

        $response = $this->actingAs($admin)->get(route('products.index'));

        $response->assertOk();
        $response->assertSee('1 produto atingiu o estoque mínimo');
        $response->assertSee('Refrigerante baixo');
        $response->assertSee('Estoque baixo');
    }

    public function test_weight_product_with_fractional_stock_cannot_change_to_unit(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->byWeight()->create(['stock_milliunits' => 1250]);

        $response = $this->actingAs($admin)->put(route('products.update', $product), [
            'name' => $product->name,
            'sale_type' => ProductSaleType::Unit->value,
            'price' => '10,00',
            'min_stock' => '',
            'active' => '1',
        ]);

        $response->assertSessionHasErrors(['sale_type' => 'Ajuste o estoque para um valor inteiro antes de alterar a venda para unidade.']);
        $this->assertSame(ProductSaleType::Weight, $product->refresh()->sale_type);
    }
}
