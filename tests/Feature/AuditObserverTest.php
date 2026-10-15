<?php

namespace Tests\Feature;

use App\AuditArea;
use App\AuditEvent;
use App\CashSessionStatus;
use App\Models\AuditLog;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditObserverTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_creation_records_actor_and_subject_without_field_values(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $customer = Customer::factory()->create(['name' => 'Maria da Silva']);

        $auditLog = AuditLog::query()->sole();
        $this->assertSame($admin->id, $auditLog->user_id);
        $this->assertSame(AuditEvent::Created, $auditLog->event);
        $this->assertSame(AuditArea::Customers, $auditLog->area);
        $this->assertSame($customer->id, $auditLog->subject_id);
        $this->assertSame('Maria da Silva', $auditLog->subject_label);
        $this->assertNull($auditLog->changed_fields);
    }

    public function test_update_records_changed_field_names_but_never_password(): void
    {
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->create();
        $this->actingAs($admin);

        $targetUser->update([
            'name' => 'Nome atualizado',
            'password' => Hash::make('nova-senha-segura'),
        ]);

        $auditLog = AuditLog::query()->sole();
        $this->assertSame(AuditEvent::Updated, $auditLog->event);
        $this->assertSame(['name'], $auditLog->changed_fields);
        $this->assertStringNotContainsString('password', json_encode($auditLog->toArray(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('nova-senha-segura', json_encode($auditLog->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_closing_cash_session_uses_specific_description(): void
    {
        $admin = User::factory()->admin()->create();
        $cashSession = CashSession::factory()->create();
        $this->actingAs($admin);

        $cashSession->update([
            'status' => CashSessionStatus::Closed,
            'closed_by' => $admin->id,
            'closed_at' => now(),
        ]);

        $auditLog = AuditLog::query()->sole();
        $this->assertSame('Caixa fechado', $auditLog->description);
        $this->assertSame(AuditArea::Cash, $auditLog->area);
    }

    public function test_change_without_authenticated_actor_is_not_recorded(): void
    {
        Customer::factory()->create();

        $this->assertDatabaseCount('audit_logs', 0);
    }
}
