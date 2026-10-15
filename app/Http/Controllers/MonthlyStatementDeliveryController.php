<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMonthlyStatementDeliveryRequest;
use App\Models\Customer;
use App\Models\MonthlyStatementDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

class MonthlyStatementDeliveryController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(StoreMonthlyStatementDeliveryRequest $request, Customer $customer): RedirectResponse
    {
        $month = $request->string('month')->toString();

        if ($customer->phone === null) {
            return back()->with('error', 'Cadastre o telefone do cliente antes de marcar o resumo como enviado.');
        }

        $statementMonth = CarbonImmutable::createFromFormat('!Y-m', $month)->startOfMonth();
        $delivery = MonthlyStatementDelivery::query()
            ->visibleForCustomerSharing()
            ->whereBelongsTo($customer)
            ->whereDate('statement_month', $statementMonth->toDateString())
            ->first() ?? new MonthlyStatementDelivery([
                'customer_id' => $customer->id,
                'statement_month' => $statementMonth,
            ]);
        $delivery->fill([
            'branch_id' => app('currentBranch')->id,
            'sent_by' => $request->user()->id,
            'sent_at' => now(),
        ])->save();

        return redirect()
            ->route('monthly-closing.index', ['month' => $month])
            ->with('success', "Resumo de {$customer->name} marcado como enviado.");
    }
}
