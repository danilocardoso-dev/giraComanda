<?php

namespace Tests\Feature;

use App\AuditArea;
use App\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('audit.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_cashier_cannot_view_audit_history(): void
    {
        $cashier = User::factory()->cashier()->create();

        $response = $this->actingAs($cashier)->get(route('audit.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_filter_audit_history_by_area(): void
    {
        $this->travelTo('2026-10-09 14:30:00');
        $admin = User::factory()->admin()->create();
        AuditLog::factory()->for($admin)->create([
            'area' => AuditArea::Products,
            'event' => AuditEvent::Updated,
            'subject_label' => 'Refrigerante',
            'description' => 'Produtos · Alteração',
        ]);
        AuditLog::factory()->for($admin)->create([
            'area' => AuditArea::Payments,
            'event' => AuditEvent::Created,
            'subject_label' => 'Pagamento #10',
            'description' => 'Pagamento registrado',
        ]);

        $response = $this->actingAs($admin)->get(route('audit.index', [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'area' => AuditArea::Products->value,
        ]));

        $response->assertOk();
        $response->assertSee('Refrigerante');
        $response->assertDontSee('Pagamento #10');
        $response->assertViewHas('auditLogs', fn ($auditLogs): bool => $auditLogs->total() === 1);
    }

    public function test_invalid_area_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->from(route('audit.index'))->get(route('audit.index', [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'area' => 'unauthorized-area',
        ]));

        $response->assertRedirect(route('audit.index'));
        $response->assertSessionHasErrors(['area' => 'Selecione uma área válida.']);
    }

    public function test_audit_history_escapes_subject_label(): void
    {
        $this->travelTo('2026-10-09 14:30:00');
        $admin = User::factory()->admin()->create();
        AuditLog::factory()->for($admin)->create([
            'subject_label' => '<script>alert(1)</script>',
        ]);

        $response = $this->actingAs($admin)->get(route('audit.index', [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]));

        $response->assertOk();
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
    }
}
