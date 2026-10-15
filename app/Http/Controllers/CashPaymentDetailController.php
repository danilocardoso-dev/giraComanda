<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class CashPaymentDetailController extends Controller
{
    public function __invoke(Payment $payment): View
    {
        $payment->load([
            'tab.card',
            'tab.items.shares.customer',
            'tab.customer',
            'customer',
            'recorder',
            'allocations.receivable' => fn ($query) => $query->visibleForCustomerSharing(),
            'allocations.receivable.branch:id,name',
            'allocations.receivable.tab' => fn ($query) => $query
                ->withoutGlobalScope('currentBranch')
                ->whereHas('branch', fn (Builder $branchQuery): Builder => $branchQuery
                    ->where('company_id', app('currentCompany')->id)),
            'allocations.receivable.tab.card' => fn ($query) => $query->withoutGlobalScope('currentBranch'),
            'allocations.receivable.tab.items.shares.customer',
        ]);

        return view('cash.partials.payment-detail', ['payment' => $payment]);
    }
}
