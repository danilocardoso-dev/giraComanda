<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanyCustomerSharingController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_sharing_enabled' => ['required', 'boolean'],
        ]);

        app('currentCompany')->update($validated);

        $message = $validated['customer_sharing_enabled']
            ? 'Compartilhamento de clientes ativado para todas as filiais.'
            : 'Compartilhamento de clientes desativado. Cada filial verá apenas seus clientes.';

        return back()->with('success', $message);
    }
}
