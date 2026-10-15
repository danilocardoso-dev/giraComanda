<?php

namespace App\Services;

use App\Contracts\DatabaseImporter;
use RuntimeException;
use Symfony\Component\Process\Process;

class MySqlDatabaseImporter implements DatabaseImporter
{
    public function import(string $sourcePath): void
    {
        $connectionName = (string) config('database.default');
        $connection = config("database.connections.{$connectionName}");

        if (! is_array($connection) || ! in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('A restauração automática está disponível somente para bancos MySQL ou MariaDB.');
        }

        $binary = (string) config('backup.mysql_binary');

        if (str_contains($binary, DIRECTORY_SEPARATOR) && ! is_file($binary)) {
            throw new RuntimeException('O programa de restauração do MySQL não foi encontrado.');
        }

        $source = fopen($sourcePath, 'rb');

        if ($source === false) {
            throw new RuntimeException('Não foi possível abrir o arquivo de backup.');
        }

        try {
            $process = new Process([
                $binary,
                '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
                '--port='.(string) ($connection['port'] ?? '3306'),
                '--user='.(string) ($connection['username'] ?? ''),
                '--default-character-set=utf8mb4',
                (string) ($connection['database'] ?? ''),
            ], null, [
                'MYSQL_PWD' => (string) ($connection['password'] ?? ''),
            ], $source);
            $process->setTimeout((float) config('backup.timeout_seconds', 300));
            $process->run();
        } finally {
            fclose($source);
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Não foi possível restaurar o banco de dados.');
        }
    }
}
