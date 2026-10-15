<?php

namespace App\Actions;

use App\Contracts\DatabaseExporter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CreateDatabaseBackup
{
    public function __construct(private DatabaseExporter $databaseExporter) {}

    public function handle(): string
    {
        $disk = Storage::disk((string) config('backup.disk', 'local'));
        $directory = trim((string) config('backup.directory', 'backups/database'), '/');
        $disk->makeDirectory($directory);
        $backupPath = $this->nextAvailablePath($disk, $directory);

        $this->databaseExporter->export($disk->path($backupPath));

        if (! $disk->exists($backupPath) || $disk->size($backupPath) === 0) {
            $disk->delete($backupPath);

            throw new RuntimeException('O arquivo de backup não foi criado corretamente.');
        }

        $this->removeExpiredBackups($disk, $directory, $backupPath);
        $this->copyToExternalDisk($disk, $backupPath);

        return $backupPath;
    }

    private function nextAvailablePath(FilesystemAdapter $disk, string $directory): string
    {
        $baseName = 'giracomanda-'.now()->format('Y-m-d_H-i-s');
        $backupPath = "{$directory}/{$baseName}.sql";
        $sequence = 2;

        while ($disk->exists($backupPath)) {
            $backupPath = "{$directory}/{$baseName}-{$sequence}.sql";
            $sequence++;
        }

        return $backupPath;
    }

    private function removeExpiredBackups(FilesystemAdapter $disk, string $directory, string $currentBackupPath): void
    {
        $retentionTimestamp = now()->subDays((int) config('backup.retention_days', 30))->timestamp;
        $maximumFiles = max(1, (int) config('backup.maximum_files', 30));
        $backupPaths = collect($disk->files($directory))
            ->filter(fn (string $path): bool => str_ends_with($path, '.sql'))
            ->sortByDesc(fn (string $path): int => $disk->lastModified($path))
            ->values();

        foreach ($backupPaths as $index => $backupPath) {
            if ($backupPath === $currentBackupPath) {
                continue;
            }

            if ($index >= $maximumFiles || $disk->lastModified($backupPath) < $retentionTimestamp) {
                $disk->delete($backupPath);
            }
        }
    }

    private function copyToExternalDisk(FilesystemAdapter $sourceDisk, string $backupPath): void
    {
        $externalDiskName = config('backup.external_disk');

        if (! is_string($externalDiskName) || $externalDiskName === '') {
            return;
        }

        $source = $sourceDisk->readStream($backupPath);

        if ($source === null) {
            throw new RuntimeException('Não foi possível ler o backup para criar a cópia externa.');
        }

        try {
            $externalDisk = Storage::disk($externalDiskName);
            $externalDisk->put($backupPath, $source);

            if (! $externalDisk->exists($backupPath) || $externalDisk->size($backupPath) === 0) {
                throw new RuntimeException('A cópia externa do backup não foi criada corretamente.');
            }
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }
        }
    }
}
