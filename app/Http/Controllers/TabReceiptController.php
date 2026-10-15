<?php

namespace App\Http\Controllers;

use App\Models\EstablishmentSetting;
use App\Models\Tab;
use App\TabStatus;
use Illuminate\Contracts\View\View;

class TabReceiptController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Tab $tab): View
    {
        abort_unless($tab->status === TabStatus::Closed, 404);

        $tab->load([
            'card',
            'customer',
            'opener',
            'closer',
            'items.shares.customer',
            'payments.recorder',
            'receivables.customer',
        ]);
        $settings = EstablishmentSetting::query()->first() ?? new EstablishmentSetting([
            'name' => config('giracomanda.establishment_name'),
        ]);

        return view('tabs.receipt', compact('tab', 'settings'));
    }
}
