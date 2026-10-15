<?php

namespace App\Http\Requests;

use App\CompanyRole;
use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ManagementReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:end_date'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'branch_id' => ['required', 'string', 'max:20'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('branch_id')) {
                return;
            }

            $branchSelection = $this->string('branch_id')->toString();
            $isCompanyAdmin = $this->user()?->companyMemberships()
                ->where('company_id', app('currentCompany')->id)
                ->where('role', CompanyRole::CompanyAdmin)
                ->where('active', true)
                ->exists() ?? false;

            if ($branchSelection === 'all') {
                if (! $isCompanyAdmin) {
                    $validator->errors()->add('branch_id', 'Você não tem permissão para visualizar o consolidado da empresa.');
                }

                return;
            }

            if (! ctype_digit($branchSelection)) {
                $validator->errors()->add('branch_id', 'Selecione uma filial válida.');

                return;
            }

            $branchId = (int) $branchSelection;
            $belongsToCompany = Branch::query()
                ->where('company_id', app('currentCompany')->id)
                ->whereKey($branchId)
                ->exists();

            if (! $belongsToCompany || (! $isCompanyAdmin && $branchId !== app('currentBranch')->id)) {
                $validator->errors()->add('branch_id', 'Selecione uma filial válida.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'start_date' => filled($this->input('start_date')) ? $this->input('start_date') : today()->startOfMonth()->toDateString(),
            'end_date' => filled($this->input('end_date')) ? $this->input('end_date') : today()->toDateString(),
            'branch_id' => filled($this->input('branch_id')) ? $this->input('branch_id') : (string) app('currentBranch')->id,
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'start_date.date_format' => 'Informe uma data inicial válida.',
            'start_date.before_or_equal' => 'A data inicial deve ser anterior ou igual à data final.',
            'end_date.date_format' => 'Informe uma data final válida.',
            'branch_id.required' => 'Selecione a abrangência do relatório.',
        ];
    }
}
