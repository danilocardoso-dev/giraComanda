<?php

namespace Tests\Feature;

use App\Models\EstablishmentSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EstablishmentSettingControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cashier_cannot_update_settings(): void
    {
        $cashier = User::factory()->cashier()->create();

        $response = $this->actingAs($cashier)->put(route('settings.update'), [
            'name' => 'Restaurante',
            'general_due_day' => 10,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('establishment_settings', 0);
    }

    public function test_admin_can_update_general_due_day(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->put(route('settings.update'), [
            'name' => 'Restaurante da Empresa',
            'general_due_day' => 20,
            'phone' => '(11) 3333-4444',
        ]);

        $settings = EstablishmentSetting::query()->sole();

        $response->assertRedirect(route('settings.edit'));
        $this->assertSame('Restaurante da Empresa', $settings->name);
        $this->assertSame(20, $settings->general_due_day);
    }

    public function test_due_day_above_thirty_one_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->put(route('settings.update'), [
            'name' => 'Restaurante',
            'general_due_day' => 32,
        ]);

        $response->assertSessionHasErrors([
            'general_due_day' => 'O dia padrão deve estar entre 1 e 31.',
        ]);
        $this->assertDatabaseCount('establishment_settings', 0);
    }

    public function test_admin_can_upload_company_logo_and_view_it(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->put(route('settings.update'), [
            'name' => 'Restaurante com Marca',
            'general_due_day' => 10,
            'logo' => $this->fakePng('marca.png'),
        ]);

        $settings = EstablishmentSetting::query()->sole();

        $response->assertRedirect(route('settings.edit'));
        $this->assertNotNull($settings->logo_path);
        Storage::disk('public')->assertExists($settings->logo_path);

        $logoResponse = $this->get(route('branding.logo'));

        $logoResponse->assertOk();
        $logoResponse->assertHeader('content-type', 'image/png');
    }

    public function test_uploading_new_logo_replaces_previous_file(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        Storage::disk('public')->put('company-logos/antiga.png', 'old-logo');
        EstablishmentSetting::factory()->create(['logo_path' => 'company-logos/antiga.png']);

        $this->actingAs($admin)->put(route('settings.update'), [
            'name' => 'Restaurante Atualizado',
            'general_due_day' => 10,
            'logo' => $this->fakePng('nova.png'),
        ])->assertRedirect(route('settings.edit'));

        $settings = EstablishmentSetting::query()->sole();

        Storage::disk('public')->assertMissing('company-logos/antiga.png');
        Storage::disk('public')->assertExists($settings->logo_path);
    }

    public function test_svg_logo_is_rejected(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->put(route('settings.update'), [
            'name' => 'Restaurante',
            'general_due_day' => 10,
            'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
        ]);

        $response->assertSessionHasErrors(['logo']);
        $this->assertDatabaseCount('establishment_settings', 0);
    }

    private function fakePng(string $name): UploadedFile
    {
        $contents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        $this->assertIsString($contents);

        return UploadedFile::fake()->createWithContent($name, $contents);
    }
}
