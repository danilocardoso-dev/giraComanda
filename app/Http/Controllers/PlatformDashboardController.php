<?php

namespace App\Http\Controllers;

use App\Actions\BuildPlatformInvoiceWhatsAppUrl;
use App\Actions\SynchronizeSubscriptionInvoices;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tab;
use App\SubscriptionStatus;
use App\TabStatus;
use Illuminate\Contracts\View\View;

class PlatformDashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        SynchronizeSubscriptionInvoices $synchronizeSubscriptionInvoices,
        BuildPlatformInvoiceWhatsAppUrl $buildPlatformInvoiceWhatsAppUrl,
    ): View {
        $synchronizeSubscriptionInvoices->handleAll();

        $monthStart = today()->startOfMonth();
        $monthEnd = today()->endOfMonth();
        $overdueInvoices = SubscriptionInvoice::query()
            ->with('subscription.company')
            ->overdue()
            ->oldest('due_date')
            ->limit(8)
            ->get();

        return view('platform.dashboard', [
            'companiesCount' => Company::query()->count(),
            'activeCompaniesCount' => Company::query()->where('active', true)->count(),
            'trialCompaniesCount' => Subscription::query()->where('status', SubscriptionStatus::Trial->value)->count(),
            'overdueCompaniesCount' => SubscriptionInvoice::query()->overdue()->distinct()->count('subscription_id'),
            'branchesCount' => Branch::query()->count(),
            'establishmentUsersCount' => CompanyMembership::query()->where('active', true)->distinct()->count('user_id'),
            'openTabsCount' => Tab::withoutGlobalScope('currentBranch')->where('status', TabStatus::Open)->count(),
            'monthlyRecurringRevenueCents' => Subscription::query()
                ->whereIn('status', [SubscriptionStatus::Trial->value, SubscriptionStatus::Active->value])
                ->sum('monthly_amount_cents'),
            'overdueAmountCents' => SubscriptionInvoice::query()->overdue()->sum('amount_cents'),
            'receivedThisMonthCents' => SubscriptionInvoice::query()
                ->whereBetween('paid_at', [$monthStart, $monthEnd])
                ->sum('amount_cents'),
            'dueThisMonthCents' => SubscriptionInvoice::query()
                ->whereNull('paid_at')
                ->whereDate('due_date', '>=', today())
                ->whereBetween('due_date', [$monthStart, $monthEnd])
                ->sum('amount_cents'),
            'recentCompanies' => Company::query()
                ->withCount(['branches', 'memberships'])
                ->with('subscription')
                ->latest('id')
                ->limit(5)
                ->get(),
            'overdueInvoices' => $overdueInvoices,
            'whatsappUrls' => $overdueInvoices->mapWithKeys(
                fn ($invoice): array => [
                    $invoice->id => $buildPlatformInvoiceWhatsAppUrl->handle($invoice->subscription->company, $invoice),
                ],
            ),
        ]);
    }
}
