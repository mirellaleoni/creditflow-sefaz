<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Copia o cabeçalho Idempotency-Key para um campo, para que possa ser validado.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Contrato: valor em centavos, inteiro e positivo
            'amount_cents' => ['required', 'integer', 'min:1', 'max:99999999999'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'O cabeçalho Idempotency-Key é obrigatório.',
            'idempotency_key.max' => 'O cabeçalho Idempotency-Key deve ter no máximo 100 caracteres.',
        ];
    }
}
