<?php

namespace Tests\Feature;

use App\CardStatus;
use App\Models\Branch;
use App\Models\Card;
use App\Models\Company;
use App\Models\Tab;
use App\Models\User;
use App\TabStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ScannedTabControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_attendant_can_open_tab_by_scanning_available_card(): void
    {
        $attendant = User::factory()->create();
        $card = Card::factory()->create(['status' => CardStatus::Available]);

        $response = $this->actingAs($attendant)->post(route('tabs.scan'), [
            'qr_token' => $card->qr_token,
        ]);

        $tab = Tab::query()->sole();

        $response->assertRedirect(route('tabs.show', $tab));
        $response->assertSessionHas('success', "Comanda do cartão {$card->code} aberta pela câmera.");
        $this->assertSame($attendant->id, $tab->opened_by);
        $this->assertSame(TabStatus::Open, $tab->status);
        $this->assertSame(CardStatus::InUse, $card->refresh()->status);
    }

    public function test_scanning_card_in_use_opens_its_existing_tab(): void
    {
        $attendant = User::factory()->create();
        $card = Card::factory()->create(['status' => CardStatus::InUse]);
        $tab = Tab::factory()->for($card)->create([
            'opened_by' => $attendant->id,
            'status' => TabStatus::Open,
        ]);

        $response = $this->actingAs($attendant)->post(route('tabs.scan'), [
            'qr_token' => $card->qr_token,
        ]);

        $response->assertRedirect(route('tabs.show', $tab));
        $response->assertSessionHas('success', "Comanda do cartão {$card->code} localizada.");
        $this->assertDatabaseCount('tabs', 1);
    }

    public function test_scanner_rejects_invalid_or_blocked_card(): void
    {
        $attendant = User::factory()->create();
        $blockedCard = Card::factory()->create(['status' => CardStatus::Blocked]);

        $this->actingAs($attendant)
            ->post(route('tabs.scan'), ['qr_token' => 'nao-e-um-uuid'])
            ->assertSessionHasErrors(['qr_token' => 'O QR Code lido não pertence a um cartão válido.']);

        $this->post(route('tabs.scan'), ['qr_token' => $blockedCard->qr_token])
            ->assertSessionHasErrors(['qr_token' => 'Este QR Code não pertence a um cartão ativo desta filial.']);

        $this->assertDatabaseCount('tabs', 0);
    }

    public function test_scanner_does_not_open_card_from_another_branch(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Company::query()->where('slug', 'empresa-inicial')->sole();
        $currentBranch = Branch::query()->where('slug', 'matriz')->sole();
        $otherBranch = Branch::factory()->for($company)->create();
        $otherCard = Card::factory()->create([
            'branch_id' => $otherBranch->id,
            'status' => CardStatus::Available,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['active_branch_id' => $currentBranch->id])
            ->post(route('tabs.scan'), ['qr_token' => $otherCard->qr_token]);

        $response->assertSessionHasErrors([
            'qr_token' => 'Este QR Code não pertence a um cartão ativo desta filial.',
        ]);
        $this->assertDatabaseCount('tabs', 0);
        $this->assertDatabaseHas('cards', [
            'id' => $otherCard->id,
            'status' => CardStatus::Available->value,
        ]);
    }

    public function test_tabs_page_contains_automatic_camera_scanner_and_manual_fallback(): void
    {
        $attendant = User::factory()->create();

        $response = $this->actingAs($attendant)->get(route('tabs.index'));

        $response->assertOk();
        $response->assertSee('data-qr-video', false);
        $response->assertSee('Leitura automática');
        $response->assertDontSee('data-qr-scanner-open', false);
        $response->assertSee(route('tabs.scan'), false);
        $response->assertSee('data-qr-permission-help', false);
        $response->assertSee('md:grid-cols-2', false);
        $response->assertSee('Nova operação');
    }

    public function test_guest_cannot_scan_card(): void
    {
        $card = Card::factory()->create();

        $this->post(route('tabs.scan'), ['qr_token' => $card->qr_token])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('tabs', 0);
    }
}
