<?php

namespace App\Console\Commands;

use App\Contracts\DatabaseImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('backup:restore {filename : Nome do arquivo SQL} {--force : Confirma a operação destrutiva sem perguntar}')]
#[Description('Restaura o banco de dados a partir de um backup local do GiraComanda')]
class RestoreDatabaseCommand extends Command
{
    public function handle(DatabaseImporter $databaseImporter): int
    {
        $filename = basename((string) $this->argument('filename'));

        if (! preg_match('/^giracomanda-\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}(?:-\d+)?\.sql$/', $filename)) {
            $this->error('Nome de arquivo de backup inválido.');

            return self::FAILURE;
        }

        $disk = Storage::disk((string) config('backup.disk', 'local'));
        $directory = trim((string) config('backup.directory', 'backups/database'), '/');
        $backupPath = "{$directory}/{$filename}";

        if (! $disk->exists($backupPath) || $disk->size($backupPath) === 0) {
            $this->error('Arquivo de backup não encontrado ou vazio.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Esta operação substituirá os dados atuais. Deseja continuar?')) {
            $this->warn('Restauração cancelada.');

            return self::FAILURE;
        }

        Artisan::call('down', ['--render' => 'errors::503']);

        try {
            $databaseImporter->import($disk->path($backupPath));
        } catch (Throwable $exception) {
            report($exception);
            $this->error('A restauração falhou. O sistema foi reativado; verifique os registros antes de tentar novamente.');

            return self::FAILURE;
        } finally {
            Artisan::call('up');
        }

        $this->info("Backup restaurado com sucesso: {$filename}");

        return self::SUCCESS;
    }
}
