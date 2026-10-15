<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());
        $status = $request->string('status', 'all')->toString();

        if (! in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'all';
        }

        $customers = Customer::query()
            ->when($status === 'active', fn ($query) => $query->where('active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('active', false))
            ->when($search !== '', function ($query) use ($search): void {
                $digits = preg_replace('/\D/', '', $search);

                $query->where(function ($query) use ($search, $digits): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");

                    if ($digits !== '') {
                        $query->orWhere('cpf', 'like', "%{$digits}%");
                    }
                });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('customers.index', compact('customers', 'search', 'status'));
    }

    public function create(): View
    {
        return view('customers.create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::query()->create($this->customerData($request->validated()));

        return redirect()
            ->route('customers.edit', $customer)
            ->with('success', 'Cliente cadastrado com sucesso.');
    }

    public function edit(Customer $customer): View
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->customerData($request->validated()));

        return redirect()
            ->route('customers.edit', $customer)
            ->with('success', 'Cliente atualizado com sucesso.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function customerData(array $validated): array
    {
        $creditLimit = $validated['credit_limit'] ?? null;

        return [
            'name' => $validated['name'],
            'cpf' => $validated['cpf'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'credit_limit_cents' => $creditLimit === null ? null : (int) round((float) $creditLimit * 100),
            'due_day' => $validated['due_day'] ?? null,
            'active' => $validated['active'],
        ];
    }
}
