<?php

namespace App\Http\Controllers;

use App\Actions\OpenTab;
use App\CardStatus;
use App\Http\Requests\StoreTabRequest;
use App\Models\Card;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Tab;
use App\PaymentMethod;
use App\TabStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TabController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status', TabStatus::Open->value)->toString();

        if (! in_array($status, [TabStatus::Open->value, TabStatus::Closed->value, TabStatus::Cancelled->value, 'all'], true)) {
            $status = TabStatus::Open->value;
        }

        $tabs = Tab::query()
            ->with(['card', 'customer'])
            ->withCount('items')
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('tabs.index', [
            'tabs' => $tabs,
            'status' => $status,
            'availableCards' => Card::query()->where('status', CardStatus::Available)->orderBy('code')->get(),
        ]);
    }

    public function store(StoreTabRequest $request, OpenTab $openTab): RedirectResponse
    {
        $card = Card::query()->findOrFail($request->integer('card_id'));
        $tab = $openTab->handle($card, $request->user());

        return redirect()->route('tabs.show', $tab)->with('success', "Comanda do cartão {$card->code} aberta.");
    }

    public function show(Tab $tab): View
    {
        $tab->load(['card', 'customer', 'opener', 'closer', 'canceller', 'items.shares.customer', 'receivables.customer', 'payments.recorder']);

        return view('tabs.show', [
            'tab' => $tab,
            'products' => Product::query()
                ->with('branchConfiguration')
                ->whereHas('branchConfiguration', fn ($query) => $query->where('active', true))
                ->orderBy('name')
                ->get(),
            'customers' => Customer::query()->where('active', true)->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }
}
