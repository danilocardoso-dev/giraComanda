<?php

namespace App\Http\Controllers;

use App\Actions\BuildCardQrCode;
use App\Models\Card;
use Illuminate\Contracts\View\View;

class CardPrintController extends Controller
{
    public function __invoke(Card $card, BuildCardQrCode $buildCardQrCode): View
    {
        return view('cards.print', [
            'card' => $card,
            'qrCodeDataUri' => $buildCardQrCode($card),
        ]);
    }
}
