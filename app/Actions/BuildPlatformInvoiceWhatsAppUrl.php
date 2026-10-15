<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\SubscriptionInvoice;

class BuildPlatformInvoiceWhatsAppUrl
{
    public function handle(Company $company, SubscriptionInvoice $invoice): ?string
    {
        $phone = $invoice->subscription->billing_phone;

        if ($phone === null) {
            return null;
        }

        $status = $invoice->due_date->isBefore(today()) ? 'vencida' : 'em aberto';
        $message = implode("\n", [
            "Olá! Esta é uma mensagem da GiraComanda sobre a assinatura do software da {$company->name}.",
            '',
            'Mensalidade: '.$invoice->reference_month->translatedFormat('F \d\e Y'),
            'Vencimento: '.$invoice->due_date->format('d/m/Y'),
            'Valor: R$ '.number_format($invoice->amount_cents / 100, 2, ',', '.'),
            'Situação: '.$status,
            '',
            'Se o pagamento já foi realizado, por favor, desconsidere esta mensagem e envie o comprovante.',
        ]);

        return 'https://wa.me/55'.$phone.'?text='.rawurlencode($message);
    }
}
