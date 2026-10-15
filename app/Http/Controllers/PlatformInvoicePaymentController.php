<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlatformInvoicePaymentRequest;
use App\Models\Company;
use App\Models\PlatformActivityLog;
use App\Models\SubscriptionInvoice;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;

class PlatformInvoicePaymentController extends Controller
{
    public function store(StorePlatformInvoicePaymentRequest $request, Company $company, SubscriptionInvoice $invoice): RedirectResponse
    {
        abort_unless($invoice->subscription?->company_id === $company->id, 404);
        abort_if($invoice->paid_at !== null, 422, 'Esta mensalidade já foi paga.');

        $validated = $request->validated();
        $invoice->update([
            'paid_at' => Carbon::parse($validated['paid_on'])->endOfDay(),
            'payment_method' => $validated['payment_method'],
        ]);

        PlatformActivityLog::record(
            $request,
            'invoice_paid',
            'Pagamento de R$ '.number_format($invoice->amount_cents / 100, 2, ',', '.').' registrado.',
            $company,
        );

        return back()->with('success', 'Pagamento da mensalidade registrado.');
    }
}
