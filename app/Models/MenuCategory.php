<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentCompany;
use Database\Factories\MenuCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'position'])]
class MenuCategory extends Model
{
    /** @use HasFactory<MenuCategoryFactory> */
    use BelongsToCurrentCompany, HasFactory;

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
