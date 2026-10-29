<?php

namespace App\Http\Requests;

use App\Models\MenuCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuCategoryRequest extends FormRequest
{
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
        /** @var MenuCategory|null $category */
        $category = $this->route('menuCategory');

        return [
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('menu_categories', 'name')
                    ->where('company_id', app('currentCompany')->id)
                    ->ignore($category),
            ],
            'position' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'position' => filled($this->input('position')) ? $this->input('position') : 0,
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da categoria.',
            'name.max' => 'O nome da categoria pode ter no máximo 80 caracteres.',
            'name.unique' => 'Já existe uma categoria com este nome.',
            'position.integer' => 'Informe uma ordem válida.',
            'position.min' => 'A ordem não pode ser negativa.',
            'position.max' => 'A ordem pode ser no máximo 999.',
        ];
    }
}
