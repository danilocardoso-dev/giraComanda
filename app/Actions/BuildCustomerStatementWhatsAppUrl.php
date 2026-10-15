<?php

namespace App\Actions;

use App\Models\Customer;

class BuildCustomerStatementWhatsAppUrl
{
    public function handle(
        Customer $customer,
        string $establishmentName,
        string $periodLabel,
        int $openingBalanceCents,
        int $chargesTotalCents,
        int $paymentsTotalCents,
        int $closingBalanceCents,
    ): ?string {
        if ($customer->phone === null) {
            return null;
        }

        $message = implode("\n", [
            "Olá, {$customer->name}! Segue seu resumo de {$periodLabel} no {$establishmentName}:",
            '',
            'Saldo anterior: R$ '.number_format($openingBalanceCents / 100, 2, ',', '.'),
            'Novas anotações: R$ '.number_format($chargesTotalCents / 100, 2, ',', '.'),
            'Pagamentos: R$ '.number_format($paymentsTotalCents / 100, 2, ',', '.'),
            'Saldo final: R$ '.number_format($closingBalanceCents / 100, 2, ',', '.'),
            '',
            'Em caso de dúvida, entre em contato conosco.',
        ]);

        return 'https://wa.me/55'.$customer->phone.'?text='.rawurlencode($message);
    }
}
