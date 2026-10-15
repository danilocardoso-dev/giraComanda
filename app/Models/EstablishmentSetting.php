<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentCompany;
use Database\Factories\EstablishmentSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['company_id', 'name', 'logo_path', 'document', 'phone', 'email', 'address', 'general_due_day', 'currency'])]
class EstablishmentSetting extends Model
{
    /** @use HasFactory<EstablishmentSettingFactory> */
    use BelongsToCurrentCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'general_due_day' => 'integer',
        ];
    }
}
