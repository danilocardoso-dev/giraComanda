<?php

namespace App\Http\Controllers;

use App\CompanyRole;
use App\Http\Requests\ResetPlatformOwnerPasswordRequest;
use App\Models\Company;
use App\Models\PlatformActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformOwnerPasswordController extends Controller
{
    public function update(ResetPlatformOwnerPasswordRequest $request, Company $company, User $user): RedirectResponse
    {
        $isOwner = $user->companyMemberships()
            ->where('company_id', $company->id)
            ->where('role', CompanyRole::CompanyAdmin)
            ->where('active', true)
            ->exists();

        abort_unless($isOwner, 404);

        $user->update([
            'password' => $request->validated('password'),
            'must_change_password' => true,
            'remember_token' => Str::random(60),
        ]);
        DB::table('sessions')->where('user_id', $user->id)->delete();

        PlatformActivityLog::record(
            $request,
            'owner_password_reset',
            "Senha temporária gerada para {$user->name}. O proprietário deverá alterá-la no próximo acesso.",
            $company,
            $user,
        );

        return back()->with('success', "Senha temporária de {$user->name} definida. A troca será exigida no próximo acesso.");
    }
}
