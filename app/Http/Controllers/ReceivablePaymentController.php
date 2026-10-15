<?php

namespace App\Http\Controllers;

use App\Actions\ReceiveCustomerPayment;
use App\Http\Requests\StoreReceivablePaymentRequest;
use App\Models\Customer;
use App\PaymentMethod;
use Illuminate\Http\RedirectResponse;

class ReceivablePaymentController extends Controller
{
    public function __invoke(StoreReceivablePaymentRequest $request, Customer $customer, ReceiveCustomerPayment $receiveCustomerPayment): RedirectResponse
    {
        $method = PaymentMethod::from($request->string('method')->toString());
        $amountCents = (int) round($request->float('amount') * 100);

        $receiveCustomerPayment->handle($customer, $request->user(), $method, $amountCents);

        return back()->with('success', 'Recebimento de R$ '.number_format($amountCents / 100, 2, ',', '.').' registrado com sucesso.');
    }
}
