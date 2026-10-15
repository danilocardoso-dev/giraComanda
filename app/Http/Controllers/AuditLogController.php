<?php

namespace App\Http\Controllers;

use App\AuditArea;
use App\AuditEvent;
use App\Http\Requests\AuditLogIndexRequest;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

class AuditLogController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(AuditLogIndexRequest $request): View
    {
        $company = app('currentCompany');
        $validated = $request->validated();
        $periodStart = CarbonImmutable::createFromFormat('!Y-m-d', $validated['start_date']);
        $periodEndExclusive = CarbonImmutable::createFromFormat('!Y-m-d', $validated['end_date'])->addDay();
        $auditLogs = AuditLog::query()
            ->with('user:id,name')
            ->where('created_at', '>=', $periodStart)
            ->where('created_at', '<', $periodEndExclusive)
            ->when($validated['area'] ?? null, fn ($query, string $area) => $query->where('area', $area))
            ->when($validated['event'] ?? null, fn ($query, string $event) => $query->where('event', $event))
            ->when($validated['user_id'] ?? null, fn ($query, string $userId) => $query->where('user_id', $userId))
            ->latest('created_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('audit.index', [
            'auditLogs' => $auditLogs,
            'areas' => AuditArea::cases(),
            'events' => AuditEvent::cases(),
            'users' => User::query()
                ->whereHas('companyMemberships', fn ($query) => $query
                    ->where('company_id', $company->id)
                    ->where('active', true))
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => $validated,
        ]);
    }
}
