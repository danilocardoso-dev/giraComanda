<?php

namespace App\Models;

use App\AuditArea;
use App\AuditEvent;
use App\Models\Concerns\BelongsToCurrentBranch;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['branch_id', 'user_id', 'event', 'area', 'subject_id', 'subject_label', 'description', 'changed_fields', 'ip_address'])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use BelongsToCurrentBranch, HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<int, string> */
    public function changedFieldLabels(): array
    {
        $labels = [
            'name' => 'nome',
            'email' => 'e-mail',
            'role' => 'perfil',
            'active' => 'situação',
            'cpf' => 'CPF',
            'phone' => 'telefone',
            'notes' => 'observações',
            'credit_limit_cents' => 'limite de crédito',
            'due_day' => 'dia de vencimento',
            'code' => 'código',
            'status' => 'situação',
            'price_cents' => 'preço',
            'stock_milliunits' => 'saldo de estoque',
            'min_stock_milliunits' => 'estoque mínimo',
            'customer_id' => 'cliente',
            'total_cents' => 'valor total',
            'closed_by' => 'responsável pelo fechamento',
            'closed_at' => 'data de fechamento',
            'cancelled_by' => 'responsável pelo cancelamento',
            'cancelled_at' => 'data do cancelamento',
            'cancellation_reason' => 'motivo do cancelamento',
            'counted_amount_cents' => 'valor contado',
            'expected_amount_cents' => 'valor esperado',
            'difference_cents' => 'diferença',
            'paid_cents' => 'valor pago',
            'paid_at' => 'data do pagamento',
            'general_due_day' => 'vencimento geral',
            'document' => 'documento',
            'address' => 'endereço',
        ];

        return collect($this->changed_fields ?? [])
            ->map(fn (string $field): string => $labels[$field] ?? str_replace('_', ' ', $field))
            ->all();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'area' => AuditArea::class,
            'subject_id' => 'integer',
            'changed_fields' => 'array',
        ];
    }
}
