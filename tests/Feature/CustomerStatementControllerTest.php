<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EstablishmentSetting;
use App\Models\Payment;
use App\Models\Receivable;
use App\Models\TabItem;
use App\Models\TabItemShare;
use App\Models\User;
use App\PaymentMethod;
use App\ProductSaleType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CustomerStatementControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cashier_can_render_printable_monthly_statement(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create(['name' => 'Cliente Mensal']);
        EstablishmentSetting::factory()->create(['name' => 'Restaurante Exemplo']);
        $receivable = Receivable::factory()->for($customer)->create([
            'amount_cents' => 4500,
            'created_at' => '2026-10-08 12:00:00',
        ]);
        $receivable->tab->update([
            'customer_id' => $customer->id,
            'closed_at' => '2026-10-08 12:34:00',
        ]);
        TabItem::factory()->for($receivable->tab)->create([
            'product_name' => 'Prato executivo',
            'sale_type' => ProductSaleType::Unit,
            'quantity_milliunits' => 1000,
            'unit_price_cents' => 4500,
            'total_cents' => 4500,
        ]);
        Payment::factory()->for($customer)->for($cashier, 'recorder')->create([
            'tab_id' => null,
            'method' => PaymentMethod::Pix,
            'amount_cents' => 1500,
            'paid_at' => '2026-10-15 12:00:00',
        ]);

        $response = $this->actingAs($cashier)->get(route('receivables.statement', [
            'customer' => $customer,
            'month' => '2026-10',
        ]));

        $response->assertOk();
        $response->assertSee('Restaurante Exemplo');
        $response->assertSee('Cliente Mensal');
        $response->assertSee('Valor devedor');
        $response->assertSee('O que foi consumido');
        $response->assertSee('08/10/2026 às 12:34');
        $response->assertSee('Prato executivo');
        $response->assertSee('1 un.');
        $response->assertDontSee($receivable->tab->card->code);
        $response->assertDontSee('Comanda');
        $response->assertSee('R$ 45,00');
        $response->assertSee('R$ 15,00');
        $response->assertSee('R$ 30,00');
        $response->assertSee('Este documento não é fiscal.');
    }

    public function test_printable_statement_shows_only_the_customers_share_of_a_consumed_product(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create(['name' => 'Cliente do Rateio']);
        $otherCustomer = Customer::factory()->create();
        $receivable = Receivable::factory()->for($customer)->create([
            'amount_cents' => 250,
            'created_at' => '2026-10-09 15:08:00',
        ]);
        $receivable->tab->update(['closed_at' => '2026-10-09 15:08:00']);
        $item = TabItem::factory()->for($receivable->tab)->create([
            'product_name' => 'Coca-Cola',
            'sale_type' => ProductSaleType::Unit,
            'quantity_milliunits' => 1000,
            'unit_price_cents' => 1000,
            'total_cents' => 1000,
        ]);
        TabItemShare::factory()->for($item, 'item')->for($customer)->create(['amount_cents' => 250]);
        TabItemShare::factory()->for($item, 'item')->for($otherCustomer)->create(['amount_cents' => 750]);

        $response = $this->actingAs($cashier)->get(route('receivables.statement', [
            'customer' => $customer,
            'month' => '2026-10',
        ]));

        $response->assertOk();
        $response->assertSee('09/10/2026 às 15:08');
        $response->assertSee('Coca-Cola');
        $response->assertSee('Parte do cliente no rateio entre 2 pessoas');
        $response->assertSee('R$ 2,50');
        $response->assertDontSee('R$ 7,50');
    }

    public function test_attendant_cannot_render_printable_monthly_statement(): void
    {
        $attendant = User::factory()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($attendant)->get(route('receivables.statement', $customer));

        $response->assertForbidden();
    }

    public function test_printable_statement_escapes_customer_name(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create(['name' => '<script>alert(1)</script>']);

        $response = $this->actingAs($cashier)->get(route('receivables.statement', $customer));

        $response->assertOk();
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
    }
}
