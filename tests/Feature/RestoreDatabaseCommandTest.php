<?php

namespace Tests\Feature;

use App\Contracts\DatabaseImporter;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class RestoreDatabaseCommandTest extends TestCase
{
    public function test_command_restores_valid_backup_after_explicit_confirmation(): void
    {
        Storage::fake('local');
        $filename = 'giracomanda-2026-10-11_20-00-00.sql';
        Storage::disk('local')->put("backups/database/{$filename}", '-- valid backup');
        $expectedPath = Storage::disk('local')->path("backups/database/{$filename}");

        $this->mock(DatabaseImporter::class, function (MockInterface $mock) use ($expectedPath): void {
            $mock->shouldReceive('import')->once()->with($expectedPath);
        });

        $this->artisan('backup:restore', ['filename' => $filename, '--force' => true])
            ->expectsOutput("Backup restaurado com sucesso: {$filename}")
            ->assertSuccessful();

        $this->assertFileDoesNotExist(storage_path('framework/down'));
    }

    public function test_command_rejects_unknown_backup_filename(): void
    {
        Storage::fake('local');
        $this->mock(DatabaseImporter::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('import');
        });

        $this->artisan('backup:restore', ['filename' => '../outro.sql', '--force' => true])
            ->expectsOutput('Nome de arquivo de backup inválido.')
            ->assertFailed();
    }
}
