<?php

namespace App\Console\Commands;

use App\Actions\CreateDatabaseBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('backup:database')]
#[Description('Cria uma cópia de segurança do banco de dados e remove arquivos vencidos')]
class BackupDatabaseCommand extends Command
{
    public function handle(CreateDatabaseBackup $createDatabaseBackup): int
    {
        $backupPath = $createDatabaseBackup->handle();

        $this->info('Backup criado com sucesso: '.basename($backupPath));

        return self::SUCCESS;
    }
}
