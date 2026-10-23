<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function rules(): array
    {
        return [
            'amount_cents' => ['required', 'integer', 'min:1', 'max:99999999999'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'O cabeçalho Idempotency-Key é obrigatório.',
            'idempotency_key.max' => 'O cabeçalho Idempotency-Key deve ter no máximo 100 caracteres.',
        ];
    }
}
