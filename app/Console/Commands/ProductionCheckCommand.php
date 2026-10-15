<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:production-check')]
#[Description('Verifica configurações obrigatórias antes de publicar o GiraComanda')]
class ProductionCheckCommand extends Command
{
    public function handle(): int
    {
        $checks = [
            'Ambiente definido como production' => app()->environment('production'),
            'Depuração desativada' => config('app.debug') === false,
            'URL configurada com HTTPS' => str_starts_with((string) config('app.url'), 'https://'),
            'Chave da aplicação configurada' => filled(config('app.key')),
            'Cookie de sessão restrito ao HTTPS' => config('session.secure') === true,
            'Sessões criptografadas' => config('session.encrypt') === true,
            'Envio de e-mail real configurado' => ! in_array(config('mail.default'), ['array', 'log'], true),
            'Banco MySQL ou MariaDB configurado' => in_array(config('database.default'), ['mysql', 'mariadb'], true),
            'Cópia externa de backup configurada' => filled(config('backup.external_disk')),
        ];

        foreach ($checks as $label => $passed) {
            $this->line(($passed ? '<fg=green>APROVADO</>' : '<fg=red>PENDENTE</>')."  {$label}");
        }

        if (in_array(false, $checks, true)) {
            $this->newLine();
            $this->error('A instalação ainda não está pronta para produção.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Configuração de produção aprovada.');

        return self::SUCCESS;
    }
}
