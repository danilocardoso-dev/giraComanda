<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EstablishmentSetting;
use App\Models\Payment;
use App\Models\Receivable;
use App\Models\Tab;
use App\Models\TabItem;
use App\Models\User;
use App\PaymentMethod;
use App\ProductSaleType;
use App\TabStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TabReceiptControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cashier_can_render_non_fiscal_receipt_for_closed_tab(): void
    {
        $cashier = User::factory()->cashier()->create(['name' => 'Operador Caixa']);
        EstablishmentSetting::factory()->create(['name' => 'Restaurante Exemplo']);
        $tab = Tab::factory()->create([
            'opened_by' => $cashier,
            'closed_by' => $cashier,
            'status' => TabStatus::Closed,
            'total_cents' => 3500,
            'closed_at' => '2026-10-09 13:00:00',
        ]);
        TabItem::factory()->for($tab)->create([
            'product_name' => 'Almoço por quilo',
            'sale_type' => ProductSaleType::Weight,
            'quantity_milliunits' => 500,
            'unit_price_cents' => 5000,
            'total_cents' => 2500,
        ]);
        TabItem::factory()->for($tab)->create([
            'product_name' => 'Suco',
            'total_cents' => 1000,
        ]);
        Payment::factory()->for($tab)->for($cashier, 'recorder')->create([
            'method' => PaymentMethod::Cash,
            'amount_cents' => 1500,
        ]);
        Payment::factory()->for($tab)->for($cashier, 'recorder')->create([
            'method' => PaymentMethod::Pix,
            'amount_cents' => 2000,
        ]);

        $response = $this->actingAs($cashier)->get(route('tabs.receipt', $tab));

        $response->assertOk();
        $response->assertSee('Restaurante Exemplo');
        $response->assertSee('Comprovante não fiscal');
        $response->assertSee('Almoço por quilo');
        $response->assertSee('0,500 kg');
        $response->assertSee('Dinheiro');
        $response->assertSee('PIX');
        $response->assertSee('R$ 35,00');
        $response->assertSee('Operador Caixa');
        $response->assertSee('Não é documento fiscal');
    }

    public function test_receipt_lists_customer_annotations_and_due_dates(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create(['name' => 'Cliente Anotação']);
        $tab = Tab::factory()->create([
            'opened_by' => $cashier,
            'closed_by' => $cashier,
            'customer_id' => $customer,
            'status' => TabStatus::Closed,
            'total_cents' => 2000,
            'closed_at' => '2026-10-09 13:00:00',
        ]);
        TabItem::factory()->for($tab)->create(['total_cents' => 2000]);
        Receivable::factory()->for($tab)->for($customer)->create([
            'amount_cents' => 2000,
            'due_date' => '2026-11-10',
        ]);

        $response = $this->actingAs($cashier)->get(route('tabs.receipt', $tab));

        $response->assertOk();
        $response->assertSee('Anotado para pagamento posterior');
        $response->assertSee('Cliente Anotação');
        $response->assertSee('10/11/2026');
        $response->assertSee('R$ 20,00');
    }

    public function test_open_tab_does_not_have_receipt(): void
    {
        $cashier = User::factory()->cashier()->create();
        $tab = Tab::factory()->create(['opened_by' => $cashier, 'status' => TabStatus::Open]);

        $response = $this->actingAs($cashier)->get(route('tabs.receipt', $tab));

        $response->assertNotFound();
    }

    public function test_attendant_cannot_render_receipt(): void
    {
        $attendant = User::factory()->create();
        $cashier = User::factory()->cashier()->create();
        $tab = Tab::factory()->create([
            'opened_by' => $cashier,
            'closed_by' => $cashier,
            'status' => TabStatus::Closed,
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($attendant)->get(route('tabs.receipt', $tab));

        $response->assertForbidden();
    }

    public function test_receipt_escapes_product_name(): void
    {
        $cashier = User::factory()->cashier()->create();
        $tab = Tab::factory()->create([
            'opened_by' => $cashier,
            'closed_by' => $cashier,
            'status' => TabStatus::Closed,
            'total_cents' => 1000,
            'closed_at' => now(),
        ]);
        TabItem::factory()->for($tab)->create(['product_name' => '<script>alert(1)</script>']);

        $response = $this->actingAs($cashier)->get(route('tabs.receipt', $tab));

        $response->assertOk();
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
    }
}
