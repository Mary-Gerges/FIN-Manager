<?php
// app/Http/Requests/Transaction/StoreTransactionRequest.php

namespace App\Http\Requests\Transaction;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates transaction creation and update
 */
class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth checked by middleware
    }

    public function rules(): array
    {
        return [
            'type'               => ['required', 'in:income,expense'],
            'amount'             => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'category_id'        => ['required', 'integer', 'exists:categories,id'],
            'description'        => ['required', 'string', 'min:2', 'max:255'],
            'notes'              => ['nullable', 'string', 'max:1000'],
            'transaction_date'   => ['required', 'date', 'before_or_equal:today'],
            'reference_number'   => ['nullable', 'string', 'max:100'],
            'is_recurring'       => ['boolean'],
            'recurring_interval' => ['nullable', 'required_if:is_recurring,true',
                                     'in:daily,weekly,monthly,yearly'],
            'tags'               => ['nullable', 'array'],
            'tags.*'             => ['string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min'                      => 'Amount must be greater than 0.',
            'transaction_date.before_or_equal' => 'Transaction date cannot be in the future.',
            'recurring_interval.required_if'   => 'Recurring interval is required for recurring transactions.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
