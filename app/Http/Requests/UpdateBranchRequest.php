<?php

namespace App\Http\Requests;

use App\Models\Branch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Branch $branch */
        $branch = $this->route('branch');
        abort_unless($branch->company_id === app('currentCompany')->id, 404);

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Branch $branch */
        $branch = $this->route('branch');

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('branches', 'name')
                    ->where('company_id', app('currentCompany')->id)
                    ->ignore($branch),
            ],
            'active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'active' => $this->boolean('active'),
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return (new StoreBranchRequest)->messages();
    }
}
