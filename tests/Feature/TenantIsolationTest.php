<?php

namespace Tests\Feature;

use App\BranchRole;
use App\CompanyRole;
use App\Models\Branch;
use App\Models\BranchMembership;
use App\Models\BranchProduct;
use App\Models\Card;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Tab;
use App\Models\User;
use App\ProductSaleType;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_branch_lists_do_not_show_records_from_another_branch(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Company::query()->where('slug', 'empresa-inicial')->sole();
        $currentBranch = Branch::query()->where('slug', 'matriz')->sole();
        $otherBranch = Branch::factory()->for($company)->create();
        Card::factory()->create(['branch_id' => $currentBranch->id, 'code' => 'CT-LOCAL']);
        Card::factory()->create(['branch_id' => $otherBranch->id, 'code' => 'CT-OUTRA']);
        Customer::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $currentBranch->id,
            'name' => 'Cliente Local',
        ]);
        Customer::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $otherBranch->id,
            'name' => 'Cliente Outra Filial',
        ]);

        $cards = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $currentBranch->id])
            ->get(route('cards.index'));
        $customers = $this->get(route('customers.index'));

        $cards->assertOk()->assertSee('CT-LOCAL')->assertDontSee('CT-OUTRA');
        $customers->assertOk()->assertSee('Cliente Local')->assertDontSee('Cliente Outra Filial');
    }

    public function test_direct_requests_for_another_branch_records_return_not_found(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Company::query()->where('slug', 'empresa-inicial')->sole();
        $currentBranch = Branch::query()->where('slug', 'matriz')->sole();
        $otherBranch = Branch::factory()->for($company)->create();
        $card = Card::factory()->create(['branch_id' => $otherBranch->id]);
        $tab = Tab::factory()->create(['branch_id' => $otherBranch->id, 'card_id' => $card->id, 'opened_by' => $admin->id]);
        $cashSession = CashSession::factory()->create(['branch_id' => $otherBranch->id, 'opened_by' => $admin->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id, 'branch_id' => $otherBranch->id]);

        $this->actingAs($admin)->withSession(['active_branch_id' => $currentBranch->id]);

        $this->get(route('cards.print', $card))->assertNotFound();
        $this->get(route('cards.print-bulk', ['cards' => [$card->id]]))->assertNotFound();
        $this->get(route('tabs.show', $tab))->assertNotFound();
        $this->post(route('cash.sessions.close', $cashSession))->assertNotFound();
        $this->get(route('customers.edit', $customer))->assertNotFound();
    }

    public function test_customer_sharing_makes_customers_visible_across_company_branches(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Company::query()->where('slug', 'empresa-inicial')->sole();
        $currentBranch = Branch::query()->where('slug', 'matriz')->sole();
        $otherBranch = Branch::factory()->for($company)->create();
        $customer = Customer::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $otherBranch->id,
            'name' => 'Cliente Compartilhado',
        ]);
        $company->update(['customer_sharing_enabled' => true]);

        $response = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $currentBranch->id])
            ->get(route('customers.edit', $customer));

        $response->assertOk()->assertSee('Cliente Compartilhado');
    }

    public function test_company_records_cannot_be_opened_from_another_company(): void
    {
        $admin = User::factory()->admin()->create();
        $currentBranch = Branch::query()->where('slug', 'matriz')->sole();
        $otherCompany = Company::factory()->create();
        $otherBranch = Branch::factory()->for($otherCompany)->create();
        $product = Product::factory()->create(['company_id' => $otherCompany->id]);
        $otherUser = User::factory()->create();
        $otherUser->companyMemberships()->delete();
        $otherUser->branchMemberships()->delete();
        CompanyMembership::factory()->for($otherCompany)->for($otherUser)->create();
        BranchMembership::factory()->for($otherBranch)->for($otherUser)->create();

        $this->actingAs($admin)->withSession(['active_branch_id' => $currentBranch->id]);

        $this->get(route('products.edit', $product))->assertNotFound();
        $this->get(route('users.edit', $otherUser))->assertNotFound();
    }

    public function test_cpf_and_product_name_can_be_reused_by_different_companies(): void
    {
        $admin = User::factory()->admin()->create();
        $currentBranch = Branch::query()->where('slug', 'matriz')->sole();
        $otherCompany = Company::factory()->create();
        $otherBranch = Branch::factory()->for($otherCompany)->create();
        Customer::factory()->create([
            'company_id' => $otherCompany->id,
            'branch_id' => $otherBranch->id,
            'cpf' => '52998224725',
        ]);
        Product::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Coca-Cola']);

        $this->actingAs($admin)->withSession(['active_branch_id' => $currentBranch->id]);

        $this->post(route('customers.store'), [
            'name' => 'Cliente Local',
            'cpf' => '529.982.247-25',
            'active' => '1',
        ])->assertSessionHasNoErrors();
        $this->post(route('products.store'), [
            'name' => 'Coca-Cola',
            'sale_type' => ProductSaleType::Unit->value,
            'price' => '10,00',
            'stock' => '',
            'min_stock' => '',
            'active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customers', ['company_id' => $currentBranch->company_id, 'cpf' => '52998224725']);
        $this->assertDatabaseHas('products', ['company_id' => $currentBranch->company_id, 'name' => 'Coca-Cola']);
    }

    public function test_membership_role_overrides_legacy_user_role_in_the_active_branch(): void
    {
        $admin = User::factory()->admin()->create();
        $branch = Branch::query()->where('slug', 'matriz')->sole();
        $admin->companyMemberships()->update(['role' => CompanyRole::Member]);
        $admin->branchMemberships()->update(['role' => BranchRole::Attendant]);

        $this->assertSame(UserRole::Attendant, $admin->roleForBranch($branch));

        $response = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('products.index'));

        $this->assertSame(UserRole::Admin, $admin->role);
        $response->assertForbidden();
    }

    public function test_prices_and_stock_are_isolated_by_branch(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Company::query()->where('slug', 'empresa-inicial')->sole();
        $currentBranch = Branch::query()->where('slug', 'matriz')->sole();
        $otherBranch = Branch::factory()->for($company)->create();
        $product = Product::factory()->create([
            'price_cents' => 1000,
            'stock_milliunits' => 10000,
        ]);
        $otherConfiguration = BranchProduct::factory()->create([
            'branch_id' => $otherBranch->id,
            'product_id' => $product->id,
            'price_cents' => 2000,
            'stock_milliunits' => 5000,
            'active' => true,
        ]);
        $card = Card::factory()->create(['branch_id' => $otherBranch->id]);
        $tab = Tab::factory()->create([
            'branch_id' => $otherBranch->id,
            'card_id' => $card->id,
            'opened_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $otherBranch->id])
            ->post(route('tabs.items.store', $tab), [
                'product_id' => $product->id,
                'quantity' => '1',
            ]);

        $response->assertRedirect(route('tabs.show', $tab));
        $this->assertDatabaseHas('tab_items', [
            'tab_id' => $tab->id,
            'product_id' => $product->id,
            'unit_price_cents' => 2000,
        ]);
        $this->assertSame(4000, $otherConfiguration->fresh()->stock_milliunits);
        $this->assertDatabaseHas('branch_products', [
            'branch_id' => $currentBranch->id,
            'product_id' => $product->id,
            'stock_milliunits' => 10000,
        ]);
    }
}
