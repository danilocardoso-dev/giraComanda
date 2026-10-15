<?php

namespace Tests\Feature;

use App\CardStatus;
use App\Models\Card;
use App\Models\EstablishmentSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CardControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_attendant_cannot_access_cards(): void
    {
        $attendant = User::factory()->create();

        $response = $this->actingAs($attendant)->get(route('cards.index'));

        $response->assertForbidden();
    }

    public function test_cashier_can_generate_cards_with_sequential_codes_and_random_tokens(): void
    {
        $cashier = User::factory()->cashier()->create();

        $firstResponse = $this->actingAs($cashier)->post(route('cards.store'));
        $secondResponse = $this->actingAs($cashier)->post(route('cards.store'));

        $cards = Card::query()->orderBy('id')->get();

        $firstResponse->assertRedirect(route('cards.index'));
        $secondResponse->assertRedirect(route('cards.index'));
        $this->assertSame('CT-001', $cards[0]->code);
        $this->assertSame('CT-002', $cards[1]->code);
        $this->assertNotSame($cards[0]->qr_token, $cards[1]->qr_token);
        $this->assertSame(CardStatus::Available, $cards[0]->status);
        $this->assertSame(CardStatus::Available, $cards[1]->status);
    }

    public function test_cashier_can_deactivate_and_reactivate_card(): void
    {
        $card = Card::factory()->create(['status' => CardStatus::Available]);
        $cashier = User::factory()->cashier()->create();

        $deactivateResponse = $this->actingAs($cashier)->put(route('cards.update', $card), [
            'status' => CardStatus::Blocked->value,
        ]);

        $deactivateResponse->assertRedirect(route('cards.index', ['status' => 'all']));
        $this->assertSame(CardStatus::Blocked, $card->refresh()->status);

        $reactivateResponse = $this->actingAs($cashier)->put(route('cards.update', $card), [
            'status' => CardStatus::Available->value,
        ]);

        $reactivateResponse->assertRedirect(route('cards.index', ['status' => 'all']));
        $this->assertSame(CardStatus::Available, $card->refresh()->status);
    }

    public function test_card_listing_shows_only_active_cards_by_default(): void
    {
        $activeCard = Card::factory()->create([
            'code' => 'CT-101',
            'status' => CardStatus::Available,
        ]);
        $inactiveCard = Card::factory()->create([
            'code' => 'CT-102',
            'status' => CardStatus::Blocked,
        ]);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('cards.index'));

        $response->assertOk();
        $response->assertSee($activeCard->code);
        $response->assertDontSee($inactiveCard->code);
    }

    public function test_card_listing_can_show_only_available_cards(): void
    {
        $availableCard = Card::factory()->create([
            'code' => 'CT-201',
            'status' => CardStatus::Available,
        ]);
        $cardInUse = Card::factory()->create([
            'code' => 'CT-202',
            'status' => CardStatus::InUse,
        ]);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('cards.index', [
            'status' => CardStatus::Available->value,
        ]));

        $response->assertOk();
        $response->assertSee($availableCard->code);
        $response->assertDontSee($cardInUse->code);
    }

    public function test_card_in_use_cannot_be_deactivated(): void
    {
        $card = Card::factory()->create(['status' => CardStatus::InUse]);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->put(route('cards.update', $card), [
            'status' => CardStatus::Blocked->value,
        ]);

        $response->assertSessionHasErrors(['status' => 'Feche a comanda antes de alterar este cartão.']);
        $this->assertSame(CardStatus::InUse, $card->refresh()->status);
    }

    public function test_cashier_can_open_printable_card_with_embedded_qr_code(): void
    {
        $card = Card::factory()->create(['code' => 'CT-123']);
        $cashier = User::factory()->cashier()->create();

        $response = $this->actingAs($cashier)->get(route('cards.print', $card));

        $response->assertOk();
        $response->assertViewIs('cards.print');
        $response->assertViewHas(
            'qrCodeDataUri',
            fn (string $value): bool => str_starts_with($value, 'data:image/svg+xml;base64,'),
        );
        $response->assertSee('CT-123');
    }

    public function test_cashier_can_print_only_the_selected_cards_in_bulk(): void
    {
        $firstCard = Card::factory()->create(['code' => 'CT-301']);
        $secondCard = Card::factory()->create(['code' => 'CT-302']);
        $unselectedCard = Card::factory()->create(['code' => 'CT-303']);
        $cashier = User::factory()->cashier()->create();

        $response = $this->actingAs($cashier)->get(route('cards.print-bulk', [
            'cards' => [$secondCard->id, $firstCard->id],
        ]));

        $response->assertOk();
        $response->assertViewIs('cards.print-bulk');
        $response->assertViewHas('cards', fn ($cards): bool => $cards->pluck('id')->all() === [$firstCard->id, $secondCard->id]);
        $response->assertViewHas('qrCodeDataUris', fn ($qrCodes): bool => $qrCodes->count() === 2);
        $response->assertSee('CT-301');
        $response->assertSee('CT-302');
        $response->assertDontSee($unselectedCard->code);
    }

    public function test_bulk_print_requires_at_least_one_selected_card(): void
    {
        $cashier = User::factory()->cashier()->create();

        $response = $this->actingAs($cashier)
            ->from(route('cards.index'))
            ->get(route('cards.print-bulk'));

        $response->assertRedirect(route('cards.index'));
        $response->assertSessionHasErrors([
            'cards' => 'Selecione pelo menos um cartão para imprimir.',
        ]);
    }

    public function test_printable_card_uses_company_logo_and_stays_reusable(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('company-logos/restaurante.png', 'logo');
        EstablishmentSetting::factory()->create([
            'name' => 'Restaurante Piloto',
            'logo_path' => 'company-logos/restaurante.png',
        ]);
        $card = Card::factory()->create(['code' => 'CT-321']);
        $cashier = User::factory()->cashier()->create();

        $response = $this->actingAs($cashier)->get(route('cards.print', $card));

        $response->assertOk();
        $response->assertSee('Restaurante Piloto');
        $response->assertSee(route('branding.logo'), false);
        $response->assertSee('Cartão reutilizável');
        $response->assertSee('CT-321');
    }

    public function test_attendant_cannot_open_printable_card(): void
    {
        $card = Card::factory()->create();
        $attendant = User::factory()->create();

        $response = $this->actingAs($attendant)->get(route('cards.print', $card));

        $response->assertForbidden();
    }

    public function test_attendant_cannot_open_bulk_card_printing(): void
    {
        $card = Card::factory()->create();
        $attendant = User::factory()->create();

        $response = $this->actingAs($attendant)->get(route('cards.print-bulk', [
            'cards' => [$card->id],
        ]));

        $response->assertForbidden();
    }
}
