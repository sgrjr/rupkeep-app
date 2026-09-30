<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The customer form (create and edit). organization_id is never read from
 * the request: the controller sets it from the actor (TASK-437).
 */
class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the controller authorizes against the policy
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:50'],
            'zip' => ['nullable', 'string', 'max:20'],
            'account_credit' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
