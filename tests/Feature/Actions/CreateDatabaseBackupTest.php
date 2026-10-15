<?php

namespace Tests\Feature\Actions;

use App\Actions\CreateDatabaseBackup;
use App\Contracts\DatabaseExporter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class CreateDatabaseBackupTest extends TestCase
{
    public function test_creates_backup_and_removes_expired_files(): void
    {
        Storage::fake('local');
        config()->set('backup.maximum_files', 2);
        config()->set('backup.retention_days', 30);
        $this->travelTo('2026-10-09 14:30:00');
        $disk = Storage::disk('local');
        $disk->put('backups/database/giracomanda-2026-10-08_14-30-00.sql', '-- recent');
        $disk->put('backups/database/giracomanda-2026-08-01_14-30-00.sql', '-- expired');
        touch($disk->path('backups/database/giracomanda-2026-10-08_14-30-00.sql'), now()->subDay()->timestamp);
        touch($disk->path('backups/database/giracomanda-2026-08-01_14-30-00.sql'), now()->subDays(60)->timestamp);
        $action = new CreateDatabaseBackup(new class implements DatabaseExporter
        {
            public function export(string $destinationPath): void
            {
                file_put_contents($destinationPath, '-- current');
            }
        });

        $backupPath = $action->handle();

        $this->assertSame('backups/database/giracomanda-2026-10-09_14-30-00.sql', $backupPath);
        $disk->assertExists($backupPath);
        $disk->assertExists('backups/database/giracomanda-2026-10-08_14-30-00.sql');
        $disk->assertMissing('backups/database/giracomanda-2026-08-01_14-30-00.sql');
    }

    public function test_rejects_empty_backup_file(): void
    {
        Storage::fake('local');
        $action = new CreateDatabaseBackup(new class implements DatabaseExporter
        {
            public function export(string $destinationPath): void
            {
                touch($destinationPath);
            }
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('O arquivo de backup não foi criado corretamente.');

        $action->handle();
    }

    public function test_copies_backup_to_configured_external_disk(): void
    {
        Storage::fake('local');
        Storage::fake('backup_external');
        config()->set('backup.external_disk', 'backup_external');
        $action = new CreateDatabaseBackup(new class implements DatabaseExporter
        {
            public function export(string $destinationPath): void
            {
                file_put_contents($destinationPath, '-- external copy');
            }
        });

        $backupPath = $action->handle();

        Storage::disk('local')->assertExists($backupPath);
        Storage::disk('backup_external')->assertExists($backupPath);
        $this->assertSame('-- external copy', Storage::disk('backup_external')->get($backupPath));
    }
}
