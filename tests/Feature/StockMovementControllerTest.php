<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\ProductSaleType;
use App\StockMovementType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StockMovementControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_register_stock_entry(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['stock_milliunits' => 5000]);

        $response = $this->actingAs($admin)->post(route('products.stock-movements.store', $product), [
            'type' => StockMovementType::Entry->value,
            'quantity' => '3',
            'description' => 'Compra do fornecedor',
        ]);

        $response->assertRedirect(route('products.stock.show', $product));
        $this->assertSame(8000, $product->refresh()->stock_milliunits);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'recorded_by' => $admin->id,
            'type' => StockMovementType::Entry->value,
            'quantity_delta_milliunits' => 3000,
            'balance_after_milliunits' => 8000,
            'description' => 'Compra do fornecedor',
        ]);
    }

    public function test_adjustment_enables_stock_control_with_counted_balance(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->byWeight()->create(['stock_milliunits' => null]);

        $response = $this->actingAs($admin)->post(route('products.stock-movements.store', $product), [
            'type' => StockMovementType::Adjustment->value,
            'quantity' => '12.750',
            'description' => 'Contagem inicial',
        ]);

        $response->assertRedirect(route('products.stock.show', $product));
        $this->assertSame(12750, $product->refresh()->stock_milliunits);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovementType::Adjustment->value,
            'quantity_delta_milliunits' => 12750,
            'balance_after_milliunits' => 12750,
        ]);
    }

    public function test_manual_out_cannot_exceed_available_stock(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['stock_milliunits' => 2000]);

        $response = $this->actingAs($admin)->post(route('products.stock-movements.store', $product), [
            'type' => StockMovementType::ManualOut->value,
            'quantity' => '3',
            'description' => 'Perda',
        ]);

        $response->assertSessionHasErrors(['quantity' => 'A saída não pode ultrapassar o saldo disponível.']);
        $this->assertSame(2000, $product->refresh()->stock_milliunits);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_fractional_manual_movement_is_rejected_for_unit_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'sale_type' => ProductSaleType::Unit,
            'stock_milliunits' => 5000,
        ]);

        $response = $this->actingAs($admin)->post(route('products.stock-movements.store', $product), [
            'type' => StockMovementType::Entry->value,
            'quantity' => '1.5',
            'description' => 'Entrada inválida',
        ]);

        $response->assertSessionHasErrors(['quantity' => 'Produtos por unidade exigem uma quantidade inteira.']);
        $this->assertSame(5000, $product->refresh()->stock_milliunits);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_cashier_cannot_manage_stock(): void
    {
        $cashier = User::factory()->cashier()->create();
        $product = Product::factory()->create(['stock_milliunits' => 5000]);

        $response = $this->actingAs($cashier)->get(route('products.stock.show', $product));

        $response->assertForbidden();
    }

    public function test_stock_history_escapes_manual_description(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['stock_milliunits' => 5000]);
        StockMovement::factory()->for($product)->for($admin, 'recorder')->create([
            'description' => '<script>alert(1)</script>',
            'balance_after_milliunits' => 5000,
        ]);

        $response = $this->actingAs($admin)->get(route('products.stock.show', $product));

        $response->assertOk();
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
    }
}
