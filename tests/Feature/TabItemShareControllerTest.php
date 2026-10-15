<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tab;
use App\Models\TabItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TabItemShareControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_ten_reais_item_is_split_into_four_equal_shares(): void
    {
        $attendant = User::factory()->create();
        $tab = Tab::factory()->create(['total_cents' => 1000]);
        $item = TabItem::factory()->for($tab)->create(['total_cents' => 1000]);
        $customers = Customer::factory()->count(4)->create();

        $response = $this->actingAs($attendant)->put(route('tabs.items.shares.store', [$tab, $item]), [
            'customer_ids' => $customers->modelKeys(),
        ]);

        $response->assertRedirect(route('tabs.show', $tab));
        $this->assertSame([250, 250, 250, 250], $item->shares()->orderBy('customer_id')->pluck('amount_cents')->all());
        $this->assertSame(1000, $item->shares()->sum('amount_cents'));
    }

    public function test_split_distributes_remainder_without_losing_cent(): void
    {
        $cashier = User::factory()->cashier()->create();
        $tab = Tab::factory()->create(['total_cents' => 1001]);
        $item = TabItem::factory()->for($tab)->create(['total_cents' => 1001]);
        $customers = Customer::factory()->count(4)->create();

        $response = $this->actingAs($cashier)->put(route('tabs.items.shares.store', [$tab, $item]), [
            'customer_ids' => $customers->modelKeys(),
        ]);

        $response->assertRedirect(route('tabs.show', $tab));
        $this->assertSame([251, 250, 250, 250], $item->shares()->orderBy('customer_id')->pluck('amount_cents')->all());
        $this->assertSame(1001, $item->shares()->sum('amount_cents'));
    }

    public function test_item_from_another_tab_returns_not_found(): void
    {
        $attendant = User::factory()->create();
        $tab = Tab::factory()->create();
        $otherItem = TabItem::factory()->create();
        $customers = Customer::factory()->count(2)->create();

        $response = $this->actingAs($attendant)->put(route('tabs.items.shares.store', [$tab, $otherItem]), [
            'customer_ids' => $customers->modelKeys(),
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('tab_item_shares', 0);
    }
}
