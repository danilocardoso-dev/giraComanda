<?php

namespace App\Services;

use App\Contracts\DatabaseExporter;
use RuntimeException;
use Symfony\Component\Process\Process;

class MySqlDatabaseExporter implements DatabaseExporter
{
    public function export(string $destinationPath): void
    {
        $connectionName = (string) config('database.default');
        $connection = config("database.connections.{$connectionName}");

        if (! is_array($connection) || ! in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('O backup automático está disponível somente para bancos MySQL ou MariaDB.');
        }

        $binary = (string) config('backup.mysql_dump_binary');

        if (str_contains($binary, DIRECTORY_SEPARATOR) && ! is_file($binary)) {
            throw new RuntimeException('O programa de backup do MySQL não foi encontrado.');
        }

        $process = new Process([
            $binary,
            '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? '3306'),
            '--user='.(string) ($connection['username'] ?? ''),
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--routines',
            '--triggers',
            '--events',
            '--default-character-set=utf8mb4',
            '--result-file='.$destinationPath,
            (string) ($connection['database'] ?? ''),
        ], null, [
            'MYSQL_PWD' => (string) ($connection['password'] ?? ''),
        ]);
        $process->setTimeout((float) config('backup.timeout_seconds', 300));
        $process->run();

        if (! $process->isSuccessful()) {
            @unlink($destinationPath);

            throw new RuntimeException('Não foi possível gerar o backup do banco de dados.');
        }
    }
}
