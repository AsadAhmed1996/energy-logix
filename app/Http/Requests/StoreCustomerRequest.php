<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:191'],
            'last_name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:customers,email'],
            'phone' => ['required', 'string', 'max:100'],
            'status' => ['required', 'string', 'in:active,inactive'],
            'address_street' => ['required', 'string', 'max:191'],
            'address_city' => ['required', 'string', 'max:191'],
            'address_state' => ['required', 'string', 'max:191'],
            'address_zip' => ['required', 'string', 'max:50'],
            'address_country' => ['required', 'string', 'max:191'],
        ];
    }
}
