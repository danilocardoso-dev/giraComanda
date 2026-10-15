<?php

namespace App\Http\Controllers;

use App\Actions\BuildCustomerStatement;
use App\Models\Customer;
use App\Models\EstablishmentSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CustomerStatementController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Customer $customer, BuildCustomerStatement $buildCustomerStatement): View
    {
        $statement = $buildCustomerStatement->handle($customer, $request->string('month')->toString());
        $settings = EstablishmentSetting::query()->first() ?? new EstablishmentSetting([
            'name' => config('giracomanda.establishment_name'),
        ]);

        return view('receivables.statement', [
            'customer' => $customer,
            'settings' => $settings,
            ...$statement,
        ]);
    }
}
