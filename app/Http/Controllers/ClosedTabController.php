<?php

namespace App\Http\Controllers;

use App\Actions\CloseTab;
use App\Http\Requests\CloseTabRequest;
use App\Models\Customer;
use App\Models\Tab;
use App\PaymentMethod;
use Illuminate\Http\RedirectResponse;

class ClosedTabController extends Controller
{
    public function __invoke(CloseTabRequest $request, Tab $tab, CloseTab $closeTab): RedirectResponse
    {
        $customerId = $request->integer('customer_id');
        $customer = $customerId > 0 ? Customer::query()->findOrFail($customerId) : null;
        $paymentMethod = PaymentMethod::from($request->string('payment_method')->toString());
        $paymentAmounts = [];

        if ($paymentMethod === PaymentMethod::Mixed) {
            foreach (PaymentMethod::immediateCases() as $method) {
                $amount = data_get($request->validated(), "payments.{$method->value}");

                if ($amount !== null) {
                    $paymentAmounts[$method->value] = (int) round((float) $amount * 100);
                }
            }
        }

        $closeTab->handle($tab, $customer, $request->user(), $paymentMethod, $paymentAmounts);

        $message = $paymentMethod->createsReceivable()
            ? "Comanda fechada com anotação para {$customer?->name}."
            : "Comanda fechada com pagamento em {$paymentMethod->label()}.";

        return redirect()->route('tabs.show', $tab)->with('success', $message);
    }
}
