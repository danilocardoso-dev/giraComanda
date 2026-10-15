<?php

namespace App\Http\Controllers;

use App\Actions\BuildCustomerStatementWhatsAppUrl;
use App\Actions\BuildMonthlyClosing;
use App\Http\Requests\MonthlyClosingIndexRequest;
use App\Models\EstablishmentSetting;
use Illuminate\Contracts\View\View;

class MonthlyClosingController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        MonthlyClosingIndexRequest $request,
        BuildMonthlyClosing $buildMonthlyClosing,
        BuildCustomerStatementWhatsAppUrl $buildCustomerStatementWhatsAppUrl,
    ): View {
        $closing = $buildMonthlyClosing->handle($request->string('month')->toString());
        $establishmentName = EstablishmentSetting::query()->value('name') ?? config('giracomanda.establishment_name');
        $whatsappUrls = $closing['customers']->getCollection()->mapWithKeys(
            fn ($customer): array => [$customer->id => $buildCustomerStatementWhatsAppUrl->handle(
                $customer,
                $establishmentName,
                $closing['period_label'],
                (int) $customer->opening_balance_cents,
                (int) $customer->period_charges_cents,
                (int) $customer->period_payments_cents,
                (int) $customer->closing_balance_cents,
            )],
        );

        return view('monthly-closing.index', [
            ...$closing,
            'whatsappUrls' => $whatsappUrls,
        ]);
    }
}
