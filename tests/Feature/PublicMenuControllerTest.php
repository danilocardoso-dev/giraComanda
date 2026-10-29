<?php

namespace Tests\Feature;

use App\CardStatus;
use App\MenuOrderStatus;
use App\Models\Branch;
use App\Models\Card;
use App\Models\Company;
use App\Models\MenuCategory;
use App\Models\MenuOrder;
use App\Models\Product;
use App\Models\ProductPhoto;
use App\Models\Subscription;
use App\Models\Tab;
use App\SubscriptionStatus;
use App\TabStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PublicMenuControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function enableOrdering(): void
    {
        Branch::query()->where('slug', 'matriz')->update(['menu_ordering_enabled' => true]);
    }

    public function test_guest_sees_only_products_published_on_menu_grouped_by_category(): void
    {
        $card = Card::factory()->create();
        $drinks = MenuCategory::factory()->create(['name' => 'Bebidas']);
        Product::factory()->create(['name' => 'Suco de laranja', 'show_on_menu' => true, 'menu_category_id' => $drinks->id, 'description' => 'Natural, 500 ml']);
        Product::factory()->create(['name' => 'Pão de queijo', 'show_on_menu' => true]);
        Product::factory()->create(['name' => 'Produto interno', 'show_on_menu' => false]);
        Product::factory()->create(['name' => 'Produto inativo', 'show_on_menu' => true, 'active' => false]);

        $response = $this->get(route('menu.show', $card->qr_token));

        $response->assertOk()
            ->assertSeeInOrder(['Bebidas', 'Suco de laranja', 'Natural, 500 ml', 'Outros', 'Pão de queijo'])
            ->assertDontSee('Produto interno')
            ->assertDontSee('Produto inativo')
            ->assertSee('Somente consulta');
    }

    public function test_menu_is_unavailable_for_unknown_or_blocked_cards_and_suspended_companies(): void
    {
        $blockedCard = Card::factory()->create(['status' => CardStatus::Blocked]);
        $card = Card::factory()->create();

        $this->get(route('menu.show', '7d0e4f5c-3e43-4a0a-9a3c-2a3f8e1d9b11'))->assertNotFound();
        $this->get(route('menu.show', $blockedCard->qr_token))->assertNotFound();

        Subscription::query()->updateOrCreate(
            ['company_id' => Company::query()->where('slug', 'empresa-inicial')->value('id')],
            ['status' => SubscriptionStatus::Trial, 'monthly_amount_cents' => 29700, 'billing_day' => 10, 'trial_ends_at' => today()->subDays(2)],
        );

        $this->get(route('menu.show', $card->qr_token))->assertNotFound();
    }

    public function test_guest_places_order_with_branch_prices_attached_to_open_tab(): void
    {
        $this->enableOrdering();
        $card = Card::factory()->create(['status' => CardStatus::InUse]);
        $tab = Tab::factory()->for($card)->create(['status' => TabStatus::Open]);
        $burger = Product::factory()->create(['name' => 'X-Burger', 'price_cents' => 2500, 'show_on_menu' => true]);
        $soda = Product::factory()->create(['name' => 'Refrigerante', 'price_cents' => 700, 'show_on_menu' => true]);

        $response = $this->post(route('menu.orders.store', $card->qr_token), [
            'customer_name' => ' Ana ',
            'notes' => 'Sem cebola',
            'items' => [
                ['product_id' => $burger->id, 'quantity' => 2],
                ['product_id' => $soda->id, 'quantity' => 1],
            ],
        ]);

        $order = MenuOrder::query()->with('items')->sole();

        $response->assertRedirect(route('menu.show', $card->qr_token))
            ->assertSessionHas('success', 'Pedido enviado! Aguarde a confirmação do atendente.');
        $this->assertSame(MenuOrderStatus::Pending, $order->status);
        $this->assertSame($tab->id, $order->tab_id);
        $this->assertSame('Ana', $order->customer_name);
        $this->assertSame('Sem cebola', $order->notes);
        $this->assertSame(5700, $order->total_cents);
        $this->assertSame([['X-Burger', 2, 5000], ['Refrigerante', 1, 700]], $order->items
            ->map(fn ($item): array => [$item->product_name, $item->quantity, $item->total_cents])
            ->all());
        $this->assertDatabaseCount('tab_items', 0);
    }

    public function test_order_is_rejected_when_ordering_is_disabled(): void
    {
        $card = Card::factory()->create();
        $product = Product::factory()->create(['show_on_menu' => true]);

        $this->post(route('menu.orders.store', $card->qr_token), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasErrors(['items' => 'Os pedidos pelo cardápio estão desativados neste momento.']);

        $this->assertDatabaseCount('menu_orders', 0);
    }

    public function test_order_rejects_products_not_orderable_from_the_menu(): void
    {
        $this->enableOrdering();
        $card = Card::factory()->create();
        $hidden = Product::factory()->create(['show_on_menu' => false]);
        $byWeight = Product::factory()->byWeight()->create(['show_on_menu' => true]);
        $otherCompanyProduct = Product::factory()->create([
            'company_id' => Company::factory()->create()->id,
            'show_on_menu' => true,
        ]);

        foreach ([$hidden, $byWeight, $otherCompanyProduct] as $product) {
            $this->post(route('menu.orders.store', $card->qr_token), [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])->assertSessionHasErrors(['items' => 'Um dos itens escolhidos não está mais disponível. Atualize o cardápio e tente novamente.']);
        }

        $this->assertDatabaseCount('menu_orders', 0);
    }

    public function test_order_respects_stock_and_pending_orders_limit(): void
    {
        $this->enableOrdering();
        $card = Card::factory()->create();
        $product = Product::factory()->create(['name' => 'Pudim', 'show_on_menu' => true, 'stock_milliunits' => 2000]);

        $this->post(route('menu.orders.store', $card->qr_token), [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ])->assertSessionHasErrors(['items' => 'Estoque insuficiente para Pudim.']);

        MenuOrder::factory()->count(3)->for($card)->create();

        $this->post(route('menu.orders.store', $card->qr_token), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasErrors(['items' => 'Aguarde o atendente confirmar os pedidos anteriores antes de enviar outro.']);

        $this->assertDatabaseCount('menu_orders', 3);
    }

    public function test_menu_only_shows_orders_and_consumption_of_the_current_visit(): void
    {
        $card = Card::factory()->create(['status' => CardStatus::InUse]);
        $previousTab = Tab::factory()->for($card)->create([
            'status' => TabStatus::Closed,
            'opened_at' => now()->subMinutes(90),
            'closed_at' => now()->subMinutes(60),
        ]);
        MenuOrder::factory()->for($card)->for($previousTab)->create(['customer_name' => 'Cliente anterior', 'status' => MenuOrderStatus::Accepted]);
        MenuOrder::factory()->for($card)->create(['created_at' => now()->subMinutes(70), 'rejection_reason' => 'Pedido antigo sem comanda']);
        $currentTab = Tab::factory()->for($card)->create(['status' => TabStatus::Open, 'total_cents' => 1200]);
        $currentTab->items()->create([
            'product_id' => Product::factory()->create()->id,
            'added_by' => $currentTab->opened_by,
            'product_name' => 'Água com gás',
            'sale_type' => 'unit',
            'quantity_milliunits' => 2000,
            'unit_price_cents' => 600,
            'total_cents' => 1200,
        ]);
        $currentOrder = MenuOrder::factory()->for($card)->for($currentTab)->create(['rejection_reason' => 'Acabou o gelo', 'status' => MenuOrderStatus::Rejected]);

        $response = $this->get(route('menu.show', $card->qr_token));

        $response->assertOk()
            ->assertSee('Seus pedidos')
            ->assertSee('Acabou o gelo')
            ->assertSee('Água com gás')
            ->assertSee('R$ 12,00')
            ->assertDontSee('Pedido antigo sem comanda');
        $this->assertSame(1, MenuOrder::query()->forCurrentVisit($card, $currentTab)->count());
    }

    public function test_photo_is_served_only_for_products_on_menu_of_the_card_company(): void
    {
        $card = Card::factory()->create();
        $hidden = Product::factory()->create(['show_on_menu' => false, 'photo_path' => 'product-photos/hidden.jpg']);
        $otherCompanyProduct = Product::factory()->create([
            'company_id' => Company::factory()->create()->id,
            'show_on_menu' => true,
            'photo_path' => 'product-photos/other.jpg',
        ]);

        $this->get(route('menu.products.photo', ['qrToken' => $card->qr_token, 'product' => $hidden]))->assertNotFound();
        $this->get(route('menu.products.photo', ['qrToken' => $card->qr_token, 'product' => $hidden, 'photo' => ProductPhoto::factory()->for($otherCompanyProduct)->create()]))->assertNotFound();
        $this->get(route('menu.products.photo', ['qrToken' => $card->qr_token, 'product' => $otherCompanyProduct]))->assertNotFound();
    }
}
