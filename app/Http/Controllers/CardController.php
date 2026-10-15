<?php

namespace App\Http\Controllers;

use App\CardStatus;
use App\Http\Requests\UpdateCardRequest;
use App\Models\Card;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CardController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());
        $status = $request->string('status', 'active')->toString();

        if (! in_array($status, ['active', CardStatus::Available->value, CardStatus::InUse->value, CardStatus::Blocked->value, 'all'], true)) {
            $status = 'active';
        }

        $cards = Card::query()
            ->when($search !== '', fn ($query) => $query->where('code', 'like', "%{$search}%"))
            ->when($status === 'active', fn ($query) => $query->whereIn('status', [CardStatus::Available->value, CardStatus::InUse->value]))
            ->when($status === CardStatus::Available->value, fn ($query) => $query->where('status', CardStatus::Available))
            ->when($status === CardStatus::InUse->value, fn ($query) => $query->where('status', CardStatus::InUse))
            ->when($status === CardStatus::Blocked->value, fn ($query) => $query->where('status', CardStatus::Blocked))
            ->orderBy('code')
            ->orderBy('id')
            ->paginate(18)
            ->withQueryString();

        return view('cards.index', [
            'cards' => $cards,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function store(): RedirectResponse
    {
        $card = DB::transaction(function (): Card {
            $card = Card::query()->create([
                'code' => 'TMP-'.Str::random(20),
                'status' => CardStatus::Available,
            ]);

            $card->update([
                'code' => sprintf('CT-%03d', $card->getKey()),
            ]);

            return $card;
        });

        return redirect()
            ->route('cards.index')
            ->with('success', "Cartão {$card->code} gerado e ativado.");
    }

    public function update(UpdateCardRequest $request, Card $card): RedirectResponse
    {
        $card->update($request->validated());

        return redirect()
            ->route('cards.index', ['status' => 'all'])
            ->with('success', "Cartão {$card->code} atualizado.");
    }
}
