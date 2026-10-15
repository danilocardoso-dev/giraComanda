<?php

namespace App\Http\Controllers;

use App\CardStatus;
use App\Models\Card;
use App\Models\Customer;
use App\Models\Tab;
use App\Models\User;
use App\TabStatus;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $company = app('currentCompany');

        return view('dashboard', [
            'activeCustomers' => Customer::query()->where('active', true)->count(),
            'availableCards' => Card::query()->where('status', CardStatus::Available)->count(),
            'blockedCards' => Card::query()->where('status', CardStatus::Blocked)->count(),
            'activeUsers' => User::query()
                ->where('active', true)
                ->whereHas('companyMemberships', fn ($query) => $query
                    ->where('company_id', $company->id)
                    ->where('active', true))
                ->count(),
            'openTabs' => Tab::query()->where('status', TabStatus::Open)->count(),
        ]);
    }
}
