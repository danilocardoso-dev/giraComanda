<?php

namespace App\Actions;

use App\Models\Card;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

class BuildCardQrCode
{
    public function __invoke(Card $card): string
    {
        $qrCode = new QrCode(
            data: $card->qr_token,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 600,
            margin: 24,
        );

        return (new SvgWriter)->write($qrCode)->getDataUri();
    }
}
