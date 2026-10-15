<?php

namespace App\Http\Controllers;

use App\Actions\BuildCustomerStatement;
use App\Actions\BuildCustomerStatementWhatsAppUrl;
use App\Actions\BuildCustomerSummary;
use App\Models\Customer;
use App\Models\EstablishmentSetting;
use App\Models\Receivable;
use App\PaymentMethod;
use App\ReceivableStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

class ReceivableController extends Controller
{
    public function index(Request $request, BuildCustomerSummary $buildCustomerSummary): View
    {
        $search = trim($request->string('q')->toString());
        $openReceivables = Receivable::query()
            ->visibleForCustomerSharing()
            ->where('status', ReceivableStatus::Open);
        $openTotalCents = $this->remainingBalance(clone $openReceivables);
        $overdueReceivables = (clone $openReceivables)->whereDate('due_date', '<', today());
        $overdueTotalCents = $this->remainingBalance(clone $overdueReceivables);
        $notDueReceivables = (clone $openReceivables)->whereDate('due_date', '>=', today());

        $customers = Customer::query()
            ->whereHas('receivables', fn ($query) => $query
                ->visibleForCustomerSharing()
                ->where('status', ReceivableStatus::Open))
            ->addSelect([
                'open_balance_cents' => Receivable::query()
                    ->visibleForCustomerSharing()
                    ->selectRaw('COALESCE(SUM(amount_cents - paid_cents), 0)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->where('status', ReceivableStatus::Open),
            ])
            ->withMin(['receivables as next_due_date' => fn ($query) => $query
                ->visibleForCustomerSharing()
                ->where('status', ReceivableStatus::Open)], 'due_date')
            ->with([
                'receivables' => fn ($query) => $query
                    ->visibleForCustomerSharing()
                    ->where('status', ReceivableStatus::Open)
                    ->oldest('due_date')
                    ->oldest('id'),
                ...$buildCustomerSummary->customerConsumptionRelations(),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $digits = preg_replace('/\D/', '', $search);

                $query->where(function ($query) use ($search, $digits): void {
                    $query->where('name', 'like', "%{$search}%");

                    if ($digits !== '') {
                        $query->orWhere('cpf', 'like', "%{$digits}%");
                    }
                });
            })
            ->orderByDesc('open_balance_cents')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('receivables.index', [
            'customers' => $customers,
            'search' => $search,
            'openTotalCents' => $openTotalCents,
            'overdueTotalCents' => $overdueTotalCents,
            'notDueTotalCents' => max(0, $openTotalCents - $overdueTotalCents),
            'openCount' => (clone $openReceivables)->count(),
            'debtorsCount' => (clone $openReceivables)->distinct()->count('customer_id'),
            'overdueCount' => (clone $overdueReceivables)->count(),
            'notDueCount' => (clone $notDueReceivables)->count(),
        ]);
    }

    public function show(
        Request $request,
        Customer $customer,
        BuildCustomerStatement $buildCustomerStatement,
        BuildCustomerStatementWhatsAppUrl $buildCustomerStatementWhatsAppUrl,
    ): View {
        $statement = $buildCustomerStatement->handle($customer, $request->string('month')->toString());
        $establishmentName = EstablishmentSetting::query()->value('name') ?? config('giracomanda.establishment_name');
        $whatsappUrl = $buildCustomerStatementWhatsAppUrl->handle(
            $customer,
            $establishmentName,
            $statement['period_label'],
            $statement['opening_balance_cents'],
            $statement['charges_total_cents'],
            $statement['payments_total_cents'],
            $statement['closing_balance_cents'],
        );

        return view('receivables.show', [
            'customer' => $customer,
            'openBalanceCents' => $this->remainingBalance($customer->receivables()
                ->visibleForCustomerSharing()
                ->where('status', ReceivableStatus::Open)),
            'paymentMethods' => PaymentMethod::immediateCases(),
            'whatsappUrl' => $whatsappUrl,
            ...$statement,
        ]);
    }

    private function remainingBalance(Builder|HasMany $query): int
    {
        return (int) $query
            ->selectRaw('COALESCE(SUM(amount_cents - paid_cents), 0) as remaining_balance')
            ->value('remaining_balance');
    }
}
