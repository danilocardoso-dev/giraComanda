<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tab;
use App\Models\User;
use App\StockMovementType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TabItemControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_weight_item_calculates_total_and_decrements_stock(): void
    {
        $attendant = User::factory()->create();
        $tab = Tab::factory()->create();
        $product = Product::factory()->byWeight()->create([
            'name' => 'Comida por quilo',
            'price_cents' => 4990,
            'stock_milliunits' => 10000,
        ]);

        $response = $this->actingAs($attendant)->post(route('tabs.items.store', $tab), [
            'product_id' => $product->id,
            'quantity' => '0.350',
        ]);

        $item = $tab->items()->sole();

        $response->assertRedirect(route('tabs.show', $tab));
        $this->assertSame(350, $item->quantity_milliunits);
        $this->assertSame(1747, $item->total_cents);
        $this->assertSame(1747, $tab->refresh()->total_cents);
        $this->assertSame(9650, $product->refresh()->stock_milliunits);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'recorded_by' => $attendant->id,
            'tab_id' => $tab->id,
            'type' => StockMovementType::Sale->value,
            'quantity_delta_milliunits' => -350,
            'balance_after_milliunits' => 9650,
        ]);
    }

    public function test_insufficient_stock_rejects_item_without_changing_tab(): void
    {
        $attendant = User::factory()->create();
        $tab = Tab::factory()->create();
        $product = Product::factory()->create(['stock_milliunits' => 1000]);

        $response = $this->actingAs($attendant)->post(route('tabs.items.store', $tab), [
            'product_id' => $product->id,
            'quantity' => '2',
        ]);

        $response->assertSessionHasErrors(['quantity' => 'Estoque insuficiente para este lançamento.']);
        $this->assertDatabaseCount('tab_items', 0);
        $this->assertSame(0, $tab->refresh()->total_cents);
        $this->assertSame(1000, $product->refresh()->stock_milliunits);
    }

    public function test_removing_item_restores_stock_and_tab_total(): void
    {
        $cashier = User::factory()->cashier()->create();
        $tab = Tab::factory()->create(['total_cents' => 2000]);
        $product = Product::factory()->create(['stock_milliunits' => 3000]);
        $item = $tab->items()->create([
            'product_id' => $product->id,
            'added_by' => $cashier->id,
            'product_name' => $product->name,
            'sale_type' => $product->sale_type,
            'quantity_milliunits' => 2000,
            'unit_price_cents' => 1000,
            'total_cents' => 2000,
        ]);

        $response = $this->actingAs($cashier)->delete(route('tabs.items.destroy', [$tab, $item]));

        $response->assertRedirect(route('tabs.show', $tab));
        $this->assertModelMissing($item);
        $this->assertSame(0, $tab->refresh()->total_cents);
        $this->assertSame(5000, $product->refresh()->stock_milliunits);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'recorded_by' => $cashier->id,
            'tab_id' => $tab->id,
            'type' => StockMovementType::SaleReversal->value,
            'quantity_delta_milliunits' => 2000,
            'balance_after_milliunits' => 5000,
        ]);
    }
}
