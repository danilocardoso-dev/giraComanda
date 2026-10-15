<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('customers.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_attendant_cannot_access_customers(): void
    {
        $attendant = User::factory()->create();

        $response = $this->actingAs($attendant)->get(route('customers.index'));

        $response->assertForbidden();
    }

    public function test_cashier_can_create_customer_with_normalized_cpf(): void
    {
        $cashier = User::factory()->cashier()->create();

        $response = $this->actingAs($cashier)->post(route('customers.store'), [
            'name' => 'Maria da Silva',
            'cpf' => '529.982.247-25',
            'phone' => '(11) 99999-0000',
            'email' => 'MARIA@EXAMPLE.COM',
            'credit_limit' => '150.75',
            'due_day' => '15',
            'active' => '1',
        ]);

        $customer = Customer::query()->sole();

        $response->assertRedirect(route('customers.edit', $customer));
        $this->assertSame('52998224725', $customer->cpf);
        $this->assertSame('11999990000', $customer->phone);
        $this->assertSame('(11) 99999-0000', $customer->formatted_phone);
        $this->assertSame(15075, $customer->credit_limit_cents);
        $this->assertSame('maria@example.com', $customer->email);
        $this->assertTrue($customer->active);
    }

    public function test_invalid_cpf_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('customers.store'), [
            'name' => 'Cliente Inválido',
            'cpf' => '111.111.111-11',
            'active' => '1',
        ]);

        $response->assertSessionHasErrors(['cpf' => 'O CPF informado é inválido.']);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('customers.store'), [
            'name' => 'Cliente com telefone inválido',
            'cpf' => '529.982.247-25',
            'phone' => '(11) 9999',
            'active' => '1',
        ]);

        $response->assertSessionHasErrors([
            'phone' => 'Informe um telefone com DDD e 10 ou 11 números.',
        ]);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_duplicate_cpf_is_rejected(): void
    {
        Customer::factory()->create(['cpf' => '52998224725']);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('customers.store'), [
            'name' => 'Outro Cliente',
            'cpf' => '529.982.247-25',
            'active' => '1',
        ]);

        $response->assertSessionHasErrors(['cpf' => 'Este CPF já está cadastrado.']);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_customer_name_is_escaped_in_listing(): void
    {
        $dangerousName = '<script>alert("xss")</script>';
        Customer::factory()->create(['name' => $dangerousName]);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('customers.index'));

        $response->assertOk();
        $response->assertSee($dangerousName);
        $response->assertDontSee($dangerousName, false);
    }

    public function test_customer_listing_can_show_only_active_customers(): void
    {
        $activeCustomer = Customer::factory()->create([
            'name' => 'Cliente Ativo do Painel',
            'active' => true,
        ]);
        $inactiveCustomer = Customer::factory()->create([
            'name' => 'Cliente Inativo do Painel',
            'active' => false,
        ]);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('customers.index', ['status' => 'active']));

        $response->assertOk();
        $response->assertSee($activeCustomer->name);
        $response->assertDontSee($inactiveCustomer->name);
    }
}
