<?php

namespace App\Http\Requests;

use App\ProductSaleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'name')->where('company_id', app('currentCompany')->id),
            ],
            'sale_type' => ['required', Rule::enum(ProductSaleType::class)],
            'price' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'stock' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'active' => ['required', 'boolean'],
            ...self::menuRules(),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function menuRules(): array
    {
        return [
            'menu_category_id' => [
                'nullable',
                'integer',
                Rule::exists('menu_categories', 'id')->where('company_id', app('currentCompany')->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'remove_photo' => ['required', 'boolean'],
            'gallery_photos' => ['nullable', 'array', 'max:'.self::MAX_GALLERY_PHOTOS],
            'gallery_photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'remove_gallery_photos' => ['nullable', 'array'],
            'remove_gallery_photos.*' => ['integer'],
            'show_on_menu' => ['required', 'boolean'],
        ];
    }

    /**
     * Extra photos a product may keep in its menu gallery, besides the main photo.
     */
    public const MAX_GALLERY_PHOTOS = 5;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'price' => $this->normalizeCurrency((string) $this->input('price')),
            'stock' => filled($this->input('stock')) ? $this->input('stock') : null,
            'min_stock' => filled($this->input('min_stock')) ? $this->input('min_stock') : null,
            'active' => $this->boolean('active'),
            ...self::normalizedMenuInput($this),
        ]);
    }

    /**
     * @return array{menu_category_id: mixed, description: ?string, remove_photo: bool, show_on_menu: bool}
     */
    public static function normalizedMenuInput(FormRequest $request): array
    {
        return [
            'menu_category_id' => filled($request->input('menu_category_id')) ? $request->input('menu_category_id') : null,
            'description' => filled($request->input('description')) ? trim((string) $request->input('description')) : null,
            'remove_photo' => $request->boolean('remove_photo'),
            'show_on_menu' => $request->boolean('show_on_menu'),
        ];
    }

    private function normalizeCurrency(string $value): string
    {
        $value = trim($value);

        if (str_contains($value, ',')) {
            return str_replace(',', '.', str_replace('.', '', $value));
        }

        return $value;
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('sale_type') === ProductSaleType::Unit->value && filled($this->input('stock')) && (float) $this->input('stock') !== floor((float) $this->input('stock'))) {
                    $validator->errors()->add('stock', 'Produtos por unidade exigem um estoque inteiro.');
                }

                if ($this->input('sale_type') === ProductSaleType::Unit->value && filled($this->input('min_stock')) && (float) $this->input('min_stock') !== floor((float) $this->input('min_stock'))) {
                    $validator->errors()->add('min_stock', 'Produtos por unidade exigem um estoque mínimo inteiro.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do produto.',
            'name.unique' => 'Já existe um produto com este nome.',
            'sale_type.required' => 'Selecione como o produto é vendido.',
            'sale_type.enum' => 'O tipo de venda selecionado é inválido.',
            'price.required' => 'Informe o preço do produto.',
            'price.numeric' => 'Informe um preço válido.',
            'price.min' => 'O preço deve ser maior que zero.',
            'stock.numeric' => 'Informe um saldo de estoque válido.',
            'stock.min' => 'O estoque não pode ser negativo.',
            'min_stock.numeric' => 'Informe um estoque mínimo válido.',
            'min_stock.min' => 'O estoque mínimo não pode ser negativo.',
            'menu_category_id.exists' => 'A categoria selecionada é inválida.',
            'description.max' => 'A descrição pode ter no máximo 500 caracteres.',
            'photo.image' => 'Envie uma imagem válida para a foto.',
            'photo.mimes' => 'A foto deve estar em PNG, JPG ou WebP.',
            'photo.max' => 'A foto deve ter no máximo 3 MB.',
            'gallery_photos.max' => 'Envie no máximo '.self::MAX_GALLERY_PHOTOS.' fotos extras.',
            'gallery_photos.*.image' => 'Envie apenas imagens nas fotos extras.',
            'gallery_photos.*.mimes' => 'As fotos extras devem estar em PNG, JPG ou WebP.',
            'gallery_photos.*.max' => 'Cada foto extra deve ter no máximo 3 MB.',
        ];
    }
}
