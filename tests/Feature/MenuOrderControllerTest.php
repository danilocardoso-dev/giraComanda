<?php

namespace Tests\Feature;

use App\CardStatus;
use App\MenuOrderStatus;
use App\Models\Branch;
use App\Models\Card;
use App\Models\Company;
use App\Models\MenuOrder;
use App\Models\Product;
use App\Models\Tab;
use App\Models\User;
use App\StockMovementType;
use App\TabStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MenuOrderControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @param  array<int, array{0: Product, 1: int}>  $lines
     */
    private function pendingOrder(Card $card, array $lines, ?Tab $tab = null): MenuOrder
    {
        $order = MenuOrder::factory()->for($card)->create(['tab_id' => $tab?->id]);

        foreach ($lines as [$product, $quantity]) {
            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'unit_price_cents' => $product->price_cents,
                'total_cents' => $product->price_cents * $quantity,
            ]);
        }

        return $order;
    }

    public function test_attendant_sees_pending_orders_and_count(): void
    {
        $attendant = User::factory()->create();
        $card = Card::factory()->create(['code' => 'CT-042']);
        $product = Product::factory()->create(['name' => 'Batata frita']);
        $order = $this->pendingOrder($card, [[$product, 2]]);
        $order->update(['notes' => 'Bem crocante']);

        $this->actingAs($attendant)->get(route('menu-orders.index'))
            ->assertOk()
            ->assertSee('Cartão CT-042')
            ->assertSee('2x Batata frita')
            ->assertSee('Bem crocante');

        $this->getJson(route('menu-orders.pending'))
            ->assertOk()
            ->assertExactJson(['count' => 1, 'latest_id' => $order->id]);
    }

    public function test_accepting_order_opens_tab_for_available_card_and_moves_stock(): void
    {
        $attendant = User::factory()->create();
        $card = Card::factory()->create(['code' => 'CT-007', 'status' => CardStatus::Available]);
        $product = Product::factory()->create(['price_cents' => 1500, 'stock_milliunits' => 10000]);
        $order = $this->pendingOrder($card, [[$product, 3]]);

        $response = $this->actingAs($attendant)->post(route('menu-orders.accept', $order));

        $tab = Tab::query()->sole();
        $order->refresh();

        $response->assertRedirect(route('menu-orders.index'))
            ->assertSessionHas('success', "Pedido #{$order->id} lançado na comanda do cartão CT-007.");
        $this->assertSame(MenuOrderStatus::Accepted, $order->status);
        $this->assertSame($tab->id, $order->tab_id);
        $this->assertSame($attendant->id, $order->reviewed_by);
        $this->assertSame(CardStatus::InUse, $card->refresh()->status);
        $this->assertSame(4500, $tab->total_cents);
        $this->assertSame(3000, $tab->items()->sole()->quantity_milliunits);
        $this->assertSame(7000, $product->refresh()->stock_milliunits);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'tab_id' => $tab->id,
            'type' => StockMovementType::Sale->value,
            'quantity_delta_milliunits' => -3000,
        ]);
    }

    public function test_accepting_order_uses_existing_open_tab_of_card(): void
    {
        $attendant = User::factory()->create();
        $card = Card::factory()->create(['status' => CardStatus::InUse]);
        $tab = Tab::factory()->for($card)->create(['status' => TabStatus::Open]);
        $order = $this->pendingOrder($card, [[Product::factory()->create(['price_cents' => 800]), 1]]);

        $this->actingAs($attendant)->post(route('menu-orders.accept', $order))->assertRedirect(route('menu-orders.index'));

        $this->assertDatabaseCount('tabs', 1);
        $this->assertSame($tab->id, $order->refresh()->tab_id);
        $this->assertSame(800, $tab->refresh()->total_cents);
    }

    public function test_accepting_order_of_closed_tab_or_without_stock_changes_nothing(): void
    {
        $attendant = User::factory()->create();
        $card = Card::factory()->create(['status' => CardStatus::Available]);
        $closedTab = Tab::factory()->for($card)->create(['status' => TabStatus::Closed, 'closed_at' => now()]);
        $closedTabOrder = $this->pendingOrder($card, [[Product::factory()->create(), 1]], $closedTab);
        $scarceProduct = Product::factory()->create(['stock_milliunits' => 1000]);
        $scarceOrder = $this->pendingOrder($card, [[Product::factory()->create(), 1], [$scarceProduct, 2]]);

        $this->actingAs($attendant)->post(route('menu-orders.accept', $closedTabOrder))
            ->assertSessionHasErrors(['menu_order' => 'A comanda deste pedido já foi fechada. Recuse o pedido e oriente o cliente.']);
        $this->post(route('menu-orders.accept', $scarceOrder))
            ->assertSessionHasErrors(['quantity' => 'Estoque insuficiente para este lançamento.']);

        $this->assertSame(MenuOrderStatus::Pending, $scarceOrder->refresh()->status);
        $this->assertDatabaseCount('tabs', 1);
        $this->assertDatabaseCount('tab_items', 0);
        $this->assertSame(CardStatus::Available, $card->refresh()->status);
    }

    public function test_attendant_rejects_order_with_reason_only_once(): void
    {
        $attendant = User::factory()->create();
        $order = $this->pendingOrder(Card::factory()->create(), [[Product::factory()->create(), 1]]);

        $this->actingAs($attendant)->post(route('menu-orders.reject', $order), ['rejection_reason' => ''])
            ->assertSessionHasErrors(['rejection_reason' => 'Informe o motivo da recusa para o cliente.']);

        $this->post(route('menu-orders.reject', $order), ['rejection_reason' => 'Cozinha fechada'])
            ->assertRedirect(route('menu-orders.index'));

        $order->refresh();
        $this->assertSame(MenuOrderStatus::Rejected, $order->status);
        $this->assertSame('Cozinha fechada', $order->rejection_reason);
        $this->assertSame($attendant->id, $order->reviewed_by);

        $this->post(route('menu-orders.reject', $order), ['rejection_reason' => 'De novo'])
            ->assertSessionHasErrors(['menu_order' => 'Este pedido já foi analisado.']);
        $this->post(route('menu-orders.accept', $order))
            ->assertSessionHasErrors(['menu_order' => 'Este pedido já foi analisado.']);
        $this->assertDatabaseCount('tabs', 0);
    }

    public function test_orders_from_another_branch_are_not_accessible(): void
    {
        $admin = User::factory()->admin()->create();
        $otherBranch = Branch::factory()->for(Company::query()->where('slug', 'empresa-inicial')->sole())->create();
        $otherCard = Card::factory()->create(['branch_id' => $otherBranch->id]);
        $order = MenuOrder::factory()->for($otherCard)->create(['branch_id' => $otherBranch->id]);

        $this->actingAs($admin)
            ->withSession(['active_branch_id' => Branch::query()->where('slug', 'matriz')->value('id')])
            ->post(route('menu-orders.accept', $order))
            ->assertNotFound();

        $this->getJson(route('menu-orders.pending'))->assertExactJson(['count' => 0, 'latest_id' => 0]);
    }

    public function test_scanner_accepts_menu_link_printed_on_new_cards(): void
    {
        $attendant = User::factory()->create();
        $card = Card::factory()->create(['status' => CardStatus::Available]);

        $this->actingAs($attendant)
            ->post(route('tabs.scan'), ['qr_token' => route('menu.show', $card->qr_token)])
            ->assertRedirect(route('tabs.show', Tab::query()->sole()));
    }
}
