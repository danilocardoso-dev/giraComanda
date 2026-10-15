<?php

namespace App\Http\Requests;

use App\AuditArea;
use App\AuditEvent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditLogIndexRequest extends FormRequest
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
            'start_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:end_date'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'area' => ['nullable', Rule::enum(AuditArea::class)],
            'event' => ['nullable', Rule::enum(AuditEvent::class)],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'start_date' => filled($this->input('start_date')) ? $this->input('start_date') : today()->startOfMonth()->toDateString(),
            'end_date' => filled($this->input('end_date')) ? $this->input('end_date') : today()->toDateString(),
            'area' => filled($this->input('area')) ? $this->input('area') : null,
            'event' => filled($this->input('event')) ? $this->input('event') : null,
            'user_id' => filled($this->input('user_id')) ? $this->input('user_id') : null,
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'start_date.date_format' => 'Informe uma data inicial válida.',
            'start_date.before_or_equal' => 'A data inicial deve ser anterior ou igual à data final.',
            'end_date.date_format' => 'Informe uma data final válida.',
            'area.enum' => 'Selecione uma área válida.',
            'event.enum' => 'Selecione uma ação válida.',
            'user_id.exists' => 'Selecione um usuário válido.',
        ];
    }
}
