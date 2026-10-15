<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlatformInvoiceRequest;
use App\Models\Company;
use App\Models\PlatformActivityLog;
use Illuminate\Http\RedirectResponse;

class PlatformInvoiceController extends Controller
{
    public function store(StorePlatformInvoiceRequest $request, Company $company): RedirectResponse
    {
        $subscription = $company->subscription()->firstOrFail();
        $validated = $request->validated();
        $invoice = $subscription->invoices()->create([
            'reference_month' => $validated['reference_month'],
            'due_date' => $validated['due_date'],
            'amount_cents' => (int) round((float) $validated['amount'] * 100),
            'notes' => $validated['notes'],
        ]);

        PlatformActivityLog::record(
            $request,
            'invoice_created',
            'Mensalidade de '.$invoice->reference_month->translatedFormat('F \d\e Y').' criada no valor de R$ '.number_format($invoice->amount_cents / 100, 2, ',', '.'),
            $company,
        );

        return back()->with('success', 'Mensalidade criada com sucesso.');
    }
}
