<?php

namespace App\Actions;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

class BuildSubscriptionPixQrCode
{
    public function __invoke(string $pixCopyPaste): string
    {
        $qrCode = new QrCode(
            data: $pixCopyPaste,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 600,
            margin: 24,
        );

        return (new SvgWriter)->write($qrCode)->getDataUri();
    }
}
