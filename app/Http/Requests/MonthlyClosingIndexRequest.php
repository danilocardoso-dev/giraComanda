<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MonthlyClosingIndexRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'month' => ['required', 'date_format:Y-m'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'month' => filled($this->input('month')) ? $this->input('month') : now()->format('Y-m'),
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['month.date_format' => 'Informe um mês válido.'];
    }
}
