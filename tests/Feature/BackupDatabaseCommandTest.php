<?php

namespace Tests\Feature;

use App\Actions\CreateDatabaseBackup;
use Mockery\MockInterface;
use Tests\TestCase;

class BackupDatabaseCommandTest extends TestCase
{
    public function test_command_creates_database_backup(): void
    {
        $this->mock(CreateDatabaseBackup::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')
                ->once()
                ->andReturn('backups/database/giracomanda-2026-10-09_14-30-00.sql');
        });

        $this->artisan('backup:database')
            ->expectsOutput('Backup criado com sucesso: giracomanda-2026-10-09_14-30-00.sql')
            ->assertSuccessful();
    }
}
