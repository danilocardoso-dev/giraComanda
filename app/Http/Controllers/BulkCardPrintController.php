<?php

namespace App\Http\Controllers;

use App\Actions\BuildCardQrCode;
use App\Http\Requests\PrintCardsRequest;
use App\Models\Card;
use Illuminate\Contracts\View\View;

class BulkCardPrintController extends Controller
{
    public function __invoke(PrintCardsRequest $request, BuildCardQrCode $buildCardQrCode): View
    {
        $cardIds = collect($request->validated('cards'))
            ->map(fn (mixed $cardId): int => (int) $cardId)
            ->values();

        $cards = Card::query()
            ->whereKey($cardIds->all())
            ->orderBy('code')
            ->orderBy('id')
            ->get();

        abort_unless($cards->count() === $cardIds->count(), 404);

        return view('cards.print-bulk', [
            'cards' => $cards,
            'qrCodeDataUris' => $cards->mapWithKeys(
                fn (Card $card): array => [$card->getKey() => $buildCardQrCode($card)],
            ),
        ]);
    }
}
