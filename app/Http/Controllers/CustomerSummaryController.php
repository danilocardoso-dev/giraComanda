<?php

namespace App\Http\Controllers;

use App\Actions\BuildCustomerSummary;
use App\Models\Customer;
use Illuminate\Contracts\View\View;

class CustomerSummaryController extends Controller
{
    public function __invoke(Customer $customer, BuildCustomerSummary $buildCustomerSummary): View
    {
        return view('customers.partials.summary', [
            'customer' => $customer,
            ...$buildCustomerSummary->handle($customer),
        ]);
    }
}
