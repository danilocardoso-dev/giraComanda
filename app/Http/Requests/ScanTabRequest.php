<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ScanTabRequest extends FormRequest
{
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
            'qr_token' => ['required', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'qr_token.required' => 'Aponte a câmera para o QR Code do cartão.',
            'qr_token.uuid' => 'O QR Code lido não pertence a um cartão válido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $scannedValue = $this->string('qr_token')->trim()->toString();

        if (preg_match('#/cardapio/([0-9a-f-]{36})/?$#i', $scannedValue, $matches) === 1) {
            $scannedValue = $matches[1];
        }

        $this->merge(['qr_token' => $scannedValue]);
    }
}
