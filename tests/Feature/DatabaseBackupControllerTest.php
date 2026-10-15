<?php

namespace Tests\Feature;

use App\Contracts\DatabaseExporter;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DatabaseBackupControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('platform.backups.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_cashier_cannot_access_backups(): void
    {
        $cashier = User::factory()->cashier()->create();

        $response = $this->actingAs($cashier)->get(route('platform.backups.index'));

        $response->assertForbidden();
    }

    public function test_company_admin_cannot_access_platform_backups(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('platform.backups.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_create_and_view_private_backup(): void
    {
        Storage::fake('local');
        $this->travelTo('2026-10-09 14:30:00');
        $this->app->instance(DatabaseExporter::class, new class implements DatabaseExporter
        {
            public function export(string $destinationPath): void
            {
                file_put_contents($destinationPath, '-- GiraComanda backup');
            }
        });
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)->post(route('platform.backups.store'));

        $response->assertRedirect(route('platform.backups.index'));
        $response->assertSessionHas('success', 'Backup criado com sucesso: giracomanda-2026-10-09_14-30-00.sql');
        Storage::disk('local')->assertExists('backups/database/giracomanda-2026-10-09_14-30-00.sql');

        $this->actingAs($admin)
            ->get(route('platform.backups.index'))
            ->assertOk()
            ->assertSee('giracomanda-2026-10-09_14-30-00.sql');
    }

    public function test_admin_sees_safe_message_when_backup_fails(): void
    {
        Storage::fake('local');
        $this->app->instance(DatabaseExporter::class, new class implements DatabaseExporter
        {
            public function export(string $destinationPath): void
            {
                throw new RuntimeException('internal database details');
            }
        });
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)->post(route('platform.backups.store'));

        $response->assertRedirect(route('platform.backups.index'));
        $response->assertSessionHas('error', 'Não foi possível criar o backup. Verifique se o MySQL do XAMPP está em execução.');
        $response->assertSessionMissing('internal database details');
        Storage::disk('local')->assertDirectoryEmpty('backups/database');
    }

    public function test_admin_can_download_existing_backup(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/database/giracomanda-2026-10-09_14-30-00.sql', '-- backup content');
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)->get(route('platform.backups.download', 'giracomanda-2026-10-09_14-30-00.sql'));

        $response->assertOk();
        $response->assertDownload('giracomanda-2026-10-09_14-30-00.sql');
        $this->assertSame('-- backup content', $response->streamedContent());
    }

    public function test_unknown_backup_cannot_be_downloaded(): void
    {
        Storage::fake('local');
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)->get(route('platform.backups.download', 'outro-arquivo.sql'));

        $response->assertNotFound();
    }
}
