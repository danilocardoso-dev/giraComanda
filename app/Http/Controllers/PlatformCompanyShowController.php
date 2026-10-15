<?php

namespace App\Http\Controllers;

use App\Actions\BuildPlatformInvoiceWhatsAppUrl;
use App\Actions\SynchronizeSubscriptionInvoices;
use App\CompanyRole;
use App\Models\Company;
use App\Models\Tab;
use Illuminate\Contracts\View\View;

class PlatformCompanyShowController extends Controller
{
    public function __invoke(
        Company $company,
        SynchronizeSubscriptionInvoices $synchronizeSubscriptionInvoices,
        BuildPlatformInvoiceWhatsAppUrl $buildPlatformInvoiceWhatsAppUrl,
    ): View {
        $subscription = $company->subscription()->firstOrFail();
        $synchronizeSubscriptionInvoices->handle($subscription);

        $company->load([
            'branches' => fn ($query) => $query->orderBy('name'),
            'memberships.user',
            'subscription.invoices' => fn ($query) => $query->latest('due_date')->limit(24),
            'platformActivityLogs' => fn ($query) => $query->with(['actor', 'targetUser'])->latest('id')->limit(15),
        ]);

        return view('platform.companies.show', [
            'company' => $company,
            'subscription' => $company->subscription,
            'whatsappUrls' => $company->subscription->invoices->mapWithKeys(
                fn ($invoice): array => [
                    $invoice->id => $buildPlatformInvoiceWhatsAppUrl->handle($company, $invoice),
                ],
            ),
            'owners' => $company->memberships
                ->where('role', CompanyRole::CompanyAdmin)
                ->pluck('user')
                ->filter(),
            'openTabsCount' => Tab::query()
                ->whereIn('branch_id', $company->branches->pluck('id'))
                ->where('status', 'open')
                ->count(),
            'paymentMethods' => [
                'pix' => 'PIX',
                'money' => 'Dinheiro',
                'credit_card' => 'Cartão de crédito',
                'debit_card' => 'Cartão de débito',
                'bank_transfer' => 'Transferência bancária',
                'other' => 'Outro',
            ],
        ]);
    }
}
