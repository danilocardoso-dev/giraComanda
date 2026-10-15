<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\MonthlyStatementDelivery;
use App\Models\Payment;
use App\Models\Receivable;
use App\Models\User;
use App\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MonthlyClosingControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('monthly-closing.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_attendant_cannot_view_monthly_closing(): void
    {
        $attendant = User::factory()->create();

        $response = $this->actingAs($attendant)->get(route('monthly-closing.index'));

        $response->assertForbidden();
    }

    public function test_cashier_views_consolidated_month_and_whatsapp_links(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create([
            'name' => 'Cliente do Fechamento',
            'phone' => '11999999999',
        ]);
        Receivable::factory()->for($customer)->create([
            'amount_cents' => 10000,
            'created_at' => '2026-09-10 12:00:00',
        ]);
        Payment::factory()->for($customer)->for($cashier, 'recorder')->create([
            'tab_id' => null,
            'method' => PaymentMethod::Pix,
            'amount_cents' => 2000,
            'paid_at' => '2026-09-20 12:00:00',
        ]);
        Receivable::factory()->for($customer)->create([
            'amount_cents' => 5000,
            'created_at' => '2026-10-08 12:00:00',
        ]);
        Payment::factory()->for($customer)->for($cashier, 'recorder')->create([
            'tab_id' => null,
            'method' => PaymentMethod::Cash,
            'amount_cents' => 3000,
            'paid_at' => '2026-10-15 12:00:00',
        ]);
        Receivable::factory()->for($customer)->create([
            'amount_cents' => 9900,
            'created_at' => '2026-11-08 12:00:00',
        ]);

        $response = $this->actingAs($cashier)->get(route('monthly-closing.index', ['month' => '2026-10']));

        $response->assertOk();
        $response->assertSee('Cliente do Fechamento');
        $response->assertSee('R$ 80,00');
        $response->assertSee('R$ 50,00');
        $response->assertSee('R$ 30,00');
        $response->assertSee('R$ 100,00');
        $response->assertSee('https://wa.me/5511999999999', false);
        $response->assertSee('Marcar como enviado');
        $response->assertSee('Total que falta receber');
        $response->assertSee('Não enviado');
        $response->assertSee('Como ler estes valores');
        $response->assertSee('A lista abaixo mostra a conta de cada cliente.');
        $response->assertSee('Outubro de 2026');
        $response->assertSee('<select id="month" name="month"', false);
    }

    public function test_sent_statement_shows_sender_and_hides_mark_button(): void
    {
        $cashier = User::factory()->cashier()->create(['name' => 'Caixa Responsável']);
        $customer = Customer::factory()->create(['phone' => '11999999999']);
        Receivable::factory()->for($customer)->create(['created_at' => '2026-10-08 12:00:00']);
        MonthlyStatementDelivery::factory()->for($customer)->for($cashier, 'sender')->create([
            'statement_month' => '2026-10-01',
            'sent_at' => '2026-10-31 18:00:00',
        ]);

        $response = $this->actingAs($cashier)->get(route('monthly-closing.index', ['month' => '2026-10']));

        $response->assertOk();
        $response->assertSee('Enviado');
        $response->assertSee('Caixa Responsável');
        $response->assertDontSee(route('monthly-closing.sent.store', $customer), false);
    }

    public function test_monthly_closing_shows_overdue_and_upcoming_balances_per_customer(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-12 12:00:00'));
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create(['name' => 'Cliente com dois vencimentos']);
        Receivable::factory()->for($customer)->create([
            'amount_cents' => 750,
            'due_date' => '2026-10-10',
            'created_at' => '2026-10-05 12:00:00',
        ]);
        Receivable::factory()->for($customer)->create([
            'amount_cents' => 250,
            'due_date' => '2026-10-15',
            'created_at' => '2026-10-06 12:00:00',
        ]);

        $response = $this->actingAs($cashier)->get(route('monthly-closing.index', ['month' => '2026-10']));

        $response->assertOk();
        $response->assertViewHas('open_total_cents', 1000);
        $response->assertViewHas('overdue_total_cents', 750);
        $response->assertViewHas('not_due_total_cents', 250);
        $response->assertSee('R$ 7,50 atrasados');
        $response->assertSee('R$ 2,50 no prazo');
        $response->assertSee('Venceu em 10/10/2026');
        $response->assertSee('Vence em 15/10/2026');
    }

    public function test_customer_with_only_future_receivable_is_not_listed_in_previous_month(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create(['name' => 'Cliente do Futuro']);
        Receivable::factory()->for($customer)->create(['created_at' => '2026-11-08 12:00:00']);

        $response = $this->actingAs($cashier)->get(route('monthly-closing.index', ['month' => '2026-10']));

        $response->assertOk();
        $response->assertDontSee('Cliente do Futuro');
    }

    public function test_shared_customer_monthly_closing_includes_other_company_branches(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Company::query()->where('slug', 'empresa-inicial')->sole();
        $originBranch = Branch::query()->where('slug', 'matriz')->sole();
        $viewingBranch = Branch::factory()->for($company)->create(['name' => 'Centro']);
        $company->update(['customer_sharing_enabled' => true]);
        $customer = Customer::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $originBranch->id,
            'name' => 'Cliente entre filiais',
        ]);
        Receivable::factory()->for($customer)->create([
            'branch_id' => $originBranch->id,
            'amount_cents' => 5000,
            'created_at' => '2026-10-08 12:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $viewingBranch->id])
            ->get(route('monthly-closing.index', ['month' => '2026-10']));

        $response->assertOk();
        $response->assertSee('Cliente entre filiais');
        $response->assertSee('R$ 50,00');
    }

    public function test_invalid_month_is_rejected(): void
    {
        $cashier = User::factory()->cashier()->create();

        $response = $this->actingAs($cashier)->from(route('monthly-closing.index'))->get(route('monthly-closing.index', [
            'month' => '2026-13',
        ]));

        $response->assertRedirect(route('monthly-closing.index'));
        $response->assertSessionHasErrors(['month' => 'Informe um mês válido.']);
    }

    public function test_monthly_closing_escapes_customer_name(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create(['name' => '<script>alert(1)</script>']);
        Receivable::factory()->for($customer)->create(['created_at' => '2026-10-08 12:00:00']);

        $response = $this->actingAs($cashier)->get(route('monthly-closing.index', ['month' => '2026-10']));

        $response->assertOk();
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
    }
}
