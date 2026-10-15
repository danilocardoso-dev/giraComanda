<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionCheckCommandTest extends TestCase
{
    public function test_command_rejects_development_configuration(): void
    {
        $this->artisan('app:production-check')
            ->expectsOutputToContain('PENDENTE')
            ->expectsOutput('A instalação ainda não está pronta para produção.')
            ->assertFailed();
    }

    public function test_command_approves_complete_production_configuration(): void
    {
        $this->app->instance('env', 'production');
        config()->set([
            'app.debug' => false,
            'app.url' => 'https://comandas.example.test',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'session.secure' => true,
            'session.encrypt' => true,
            'mail.default' => 'smtp',
            'database.default' => 'mysql',
            'backup.external_disk' => 'backup_external',
        ]);

        $this->artisan('app:production-check')
            ->expectsOutputToContain('APROVADO')
            ->expectsOutput('Configuração de produção aprovada.')
            ->assertSuccessful();
    }
}
