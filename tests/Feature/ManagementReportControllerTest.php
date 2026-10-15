<?php

namespace Tests\Feature;

use App\BranchRole;
use App\CompanyRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\Tab;
use App\Models\TabItem;
use App\Models\User;
use App\PaymentMethod;
use App\ProductSaleType;
use App\ReceivableStatus;
use App\TabStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ManagementReportControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('reports.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_cashier_cannot_view_management_reports(): void
    {
        $cashier = User::factory()->cashier()->create();

        $response = $this->actingAs($cashier)->get(route('reports.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_view_calculated_report_for_selected_period(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        $firstTab = $this->closedTab($admin, 5000, '2026-10-05 12:00:00');
        $secondTab = $this->closedTab($admin, 3000, '2026-10-06 12:00:00');
        $outsideTab = $this->closedTab($admin, 9000, '2026-09-30 12:00:00');

        TabItem::factory()->for($firstTab)->create([
            'added_by' => $admin,
            'product_name' => 'Coca-Cola',
            'quantity_milliunits' => 2000,
            'total_cents' => 2000,
        ]);
        TabItem::factory()->for($secondTab)->create([
            'added_by' => $admin,
            'product_name' => 'Coca-Cola',
            'quantity_milliunits' => 3000,
            'total_cents' => 3000,
        ]);
        TabItem::factory()->for($firstTab)->create([
            'added_by' => $admin,
            'product_name' => 'Refeição',
            'sale_type' => ProductSaleType::Weight,
            'quantity_milliunits' => 600,
            'total_cents' => 3000,
        ]);
        TabItem::factory()->for($outsideTab)->create([
            'added_by' => $admin,
            'product_name' => 'Fora do período',
            'total_cents' => 9000,
        ]);

        Payment::factory()->for($firstTab)->create([
            'recorded_by' => $admin,
            'method' => PaymentMethod::Cash,
            'amount_cents' => 2000,
            'paid_at' => '2026-10-05 12:10:00',
        ]);
        Payment::factory()->for($secondTab)->create([
            'recorded_by' => $admin,
            'method' => PaymentMethod::Pix,
            'amount_cents' => 1000,
            'paid_at' => '2026-10-06 12:10:00',
        ]);
        Payment::factory()->for($outsideTab)->create([
            'recorded_by' => $admin,
            'amount_cents' => 9000,
            'paid_at' => '2026-09-30 12:10:00',
        ]);

        Receivable::factory()->for($customer)->for($secondTab)->create([
            'amount_cents' => 3000,
            'created_at' => '2026-10-06 12:00:00',
        ]);
        Receivable::factory()->for($customer)->for($outsideTab)->create([
            'amount_cents' => 9000,
            'status' => ReceivableStatus::Paid,
            'paid_cents' => 9000,
            'paid_at' => '2026-09-30 12:10:00',
            'created_at' => '2026-09-30 12:00:00',
        ]);

        Product::factory()->create([
            'name' => 'Suco com estoque baixo',
            'stock_milliunits' => 2000,
            'min_stock_milliunits' => 3000,
        ]);

        $response = $this->actingAs($admin)->get(route('reports.index', [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]));

        $response->assertOk();
        $response->assertViewHas('grossSalesCents', 8000);
        $response->assertViewHas('closedTabsCount', 2);
        $response->assertViewHas('averageTicketCents', 4000);
        $response->assertViewHas('receivedTotalCents', 3000);
        $response->assertViewHas('annotationsTotalCents', 3000);
        $response->assertViewHas('openReceivablesCents', 3000);
        $response->assertSee('Falta receber hoje');
        $response->assertDontSee('Total pendente');
        $response->assertViewHas('topProducts', fn (Collection $products): bool => $products->first()->product_name === 'Coca-Cola'
            && (int) $products->first()->total_cents === 5000);
        $response->assertSee('Suco com estoque baixo');
        $response->assertDontSee('Fora do período');
    }

    public function test_invalid_period_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->from(route('reports.index'))->get(route('reports.index', [
            'start_date' => '2026-10-31',
            'end_date' => '2026-10-01',
        ]));

        $response->assertRedirect(route('reports.index'));
        $response->assertSessionHasErrors('start_date');
    }

    public function test_company_admin_can_view_a_consolidated_report_without_other_company_data(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Company::query()->where('slug', 'empresa-inicial')->sole();
        $mainBranch = Branch::query()->where('slug', 'matriz')->sole();
        $secondBranch = Branch::factory()->for($company)->create(['name' => 'Unidade Centro']);
        $otherCompany = Company::factory()->create();
        $otherBranch = Branch::factory()->for($otherCompany)->create();
        $this->closedTab($admin, 5000, '2026-10-05 12:00:00', $mainBranch);
        $this->closedTab($admin, 3000, '2026-10-06 12:00:00', $secondBranch);
        $this->closedTab($admin, 9900, '2026-10-07 12:00:00', $otherBranch);

        $response = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $mainBranch->id])
            ->get(route('reports.index', [
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
                'branch_id' => 'all',
            ]));

        $response->assertOk();
        $response->assertViewHas('grossSalesCents', 8000);
        $response->assertViewHas('closedTabsCount', 2);
        $response->assertViewHas('selectedBranch', null);
        $response->assertSee('Todas as filiais');
        $response->assertSee('Unidade Centro');
    }

    public function test_company_admin_can_filter_the_report_by_one_branch(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Company::query()->where('slug', 'empresa-inicial')->sole();
        $mainBranch = Branch::query()->where('slug', 'matriz')->sole();
        $secondBranch = Branch::factory()->for($company)->create(['name' => 'Unidade Centro']);
        $this->closedTab($admin, 5000, '2026-10-05 12:00:00', $mainBranch);
        $this->closedTab($admin, 3000, '2026-10-06 12:00:00', $secondBranch);

        $response = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $mainBranch->id])
            ->get(route('reports.index', [
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
                'branch_id' => $secondBranch->id,
            ]));

        $response->assertOk();
        $response->assertViewHas('grossSalesCents', 3000);
        $response->assertViewHas('closedTabsCount', 1);
        $response->assertViewHas('selectedBranch', fn (Branch $branch): bool => $branch->is($secondBranch));
        $response->assertSee('Unidade Centro');
    }

    public function test_branch_manager_cannot_request_the_company_consolidated_report(): void
    {
        $manager = User::factory()->admin()->create();
        $branch = Branch::query()->where('slug', 'matriz')->sole();
        $manager->companyMemberships()->update(['role' => CompanyRole::Member]);
        $manager->branchMemberships()->update(['role' => BranchRole::Manager]);

        $response = $this->actingAs($manager)
            ->withSession(['active_branch_id' => $branch->id])
            ->from(route('reports.index'))
            ->get(route('reports.index', [
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
                'branch_id' => 'all',
            ]));

        $response->assertRedirect(route('reports.index'));
        $response->assertSessionHasErrors([
            'branch_id' => 'Você não tem permissão para visualizar o consolidado da empresa.',
        ]);
    }

    public function test_report_escapes_product_name(): void
    {
        $admin = User::factory()->admin()->create();
        $tab = $this->closedTab($admin, 1000, '2026-10-05 12:00:00');
        TabItem::factory()->for($tab)->create([
            'added_by' => $admin,
            'product_name' => '<script>alert(1)</script>',
        ]);

        $response = $this->actingAs($admin)->get(route('reports.index', [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]));

        $response->assertOk();
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
    }

    private function closedTab(User $admin, int $totalCents, string $closedAt, ?Branch $branch = null): Tab
    {
        return Tab::factory()->create([
            'branch_id' => $branch?->id ?? Branch::query()->where('slug', 'matriz')->value('id'),
            'opened_by' => $admin,
            'closed_by' => $admin,
            'status' => TabStatus::Closed,
            'total_cents' => $totalCents,
            'opened_at' => $closedAt,
            'closed_at' => $closedAt,
        ]);
    }
}
