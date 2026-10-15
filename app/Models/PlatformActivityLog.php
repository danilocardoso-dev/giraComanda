<?php

namespace App\Models;

use Database\Factories\PlatformActivityLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

#[Fillable(['platform_admin_user_id', 'company_id', 'target_user_id', 'action', 'description', 'ip_address'])]
class PlatformActivityLog extends Model
{
    /** @use HasFactory<PlatformActivityLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'platform_admin_user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public static function record(Request $request, string $action, string $description, ?Company $company = null, ?User $targetUser = null): self
    {
        return self::query()->create([
            'platform_admin_user_id' => $request->user()?->id,
            'company_id' => $company?->id,
            'target_user_id' => $targetUser?->id,
            'action' => $action,
            'description' => $description,
            'ip_address' => $request->ip(),
        ]);
    }
}
