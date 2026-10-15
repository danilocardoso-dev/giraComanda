<?php

namespace App\Observers;

use App\AuditArea;
use App\AuditEvent;
use App\CashSessionStatus;
use App\Models\AuditLog;
use App\Models\Card;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\EstablishmentSetting;
use App\Models\MonthlyStatementDelivery;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\StockMovement;
use App\Models\Tab;
use App\Models\TabItem;
use App\Models\TabItemShare;
use App\Models\User;
use App\TabStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditObserver
{
    /** @var array<int, string> */
    private const EXCLUDED_FIELDS = [
        'created_at',
        'updated_at',
        'password',
        'remember_token',
        'qr_token',
    ];

    public function created(Model $model): void
    {
        $this->record($model, AuditEvent::Created);
    }

    public function updated(Model $model): void
    {
        $changedFields = array_values(array_diff(array_keys($model->getChanges()), self::EXCLUDED_FIELDS));

        if ($changedFields === []) {
            return;
        }

        $this->record($model, AuditEvent::Updated, $changedFields);
    }

    public function deleted(Model $model): void
    {
        $this->record($model, AuditEvent::Deleted);
    }

    /** @param array<int, string>|null $changedFields */
    private function record(Model $model, AuditEvent $event, ?array $changedFields = null): void
    {
        $userId = Auth::id();

        if ($userId === null) {
            return;
        }

        $area = $this->areaFor($model);

        AuditLog::query()->create([
            'user_id' => $userId,
            'event' => $event,
            'area' => $area,
            'subject_id' => $model->getKey(),
            'subject_label' => $this->subjectLabel($model),
            'description' => $this->description($model, $event, $area),
            'changed_fields' => $changedFields,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    private function areaFor(Model $model): AuditArea
    {
        return match (true) {
            $model instanceof User => AuditArea::Users,
            $model instanceof Customer => AuditArea::Customers,
            $model instanceof Card => AuditArea::Cards,
            $model instanceof Product => AuditArea::Products,
            $model instanceof StockMovement => AuditArea::Stock,
            $model instanceof CashSession, $model instanceof CashMovement => AuditArea::Cash,
            $model instanceof Tab, $model instanceof TabItem, $model instanceof TabItemShare => AuditArea::Tabs,
            $model instanceof Receivable => AuditArea::Receivables,
            $model instanceof MonthlyStatementDelivery => AuditArea::Receivables,
            $model instanceof Payment => AuditArea::Payments,
            $model instanceof EstablishmentSetting => AuditArea::Settings,
            default => throw new \LogicException('Modelo não configurado para auditoria.'),
        };
    }

    private function subjectLabel(Model $model): string
    {
        return match (true) {
            $model instanceof User, $model instanceof Customer, $model instanceof Product => (string) $model->getAttribute('name'),
            $model instanceof Card => (string) $model->getAttribute('code'),
            $model instanceof Tab => 'Comanda #'.$model->getKey(),
            $model instanceof TabItem => (string) $model->getAttribute('product_name'),
            $model instanceof TabItemShare => 'Divisão #'.$model->getKey(),
            $model instanceof CashSession => 'Caixa #'.$model->getKey(),
            $model instanceof CashMovement => 'Movimento #'.$model->getKey(),
            $model instanceof StockMovement => 'Movimento #'.$model->getKey(),
            $model instanceof Receivable => 'Anotação #'.$model->getKey(),
            $model instanceof MonthlyStatementDelivery => 'Resumo mensal #'.$model->getKey(),
            $model instanceof Payment => 'Pagamento #'.$model->getKey(),
            $model instanceof EstablishmentSetting => (string) $model->getAttribute('name'),
            default => class_basename($model).' #'.$model->getKey(),
        };
    }

    private function description(Model $model, AuditEvent $event, AuditArea $area): string
    {
        if ($model instanceof Tab && $event === AuditEvent::Updated && $model->status === TabStatus::Closed) {
            return 'Comanda fechada';
        }

        if ($model instanceof Tab && $event === AuditEvent::Updated && $model->status === TabStatus::Cancelled) {
            return 'Comanda cancelada: '.$model->cancellation_reason;
        }

        if ($model instanceof CashSession && $event === AuditEvent::Updated && $model->status === CashSessionStatus::Closed) {
            return 'Caixa fechado';
        }

        return match ([$model::class, $event]) {
            [CashSession::class, AuditEvent::Created] => 'Caixa aberto',
            [CashMovement::class, AuditEvent::Created] => 'Movimento de caixa registrado',
            [StockMovement::class, AuditEvent::Created] => 'Movimento de estoque registrado',
            [Tab::class, AuditEvent::Created] => 'Comanda aberta',
            [TabItem::class, AuditEvent::Created] => 'Item lançado na comanda',
            [TabItem::class, AuditEvent::Deleted] => 'Item removido da comanda',
            [TabItemShare::class, AuditEvent::Created] => 'Divisão de item registrada',
            [Payment::class, AuditEvent::Created] => 'Pagamento registrado',
            [Receivable::class, AuditEvent::Created] => 'Anotação criada',
            [MonthlyStatementDelivery::class, AuditEvent::Created] => 'Resumo mensal marcado como enviado',
            [MonthlyStatementDelivery::class, AuditEvent::Updated] => 'Envio do resumo mensal atualizado',
            default => $area->label().' · '.$event->label(),
        };
    }
}
