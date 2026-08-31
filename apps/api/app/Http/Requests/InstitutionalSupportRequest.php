<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class InstitutionalSupportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:120'],
            'email' => ['bail', 'required', 'string', 'email:rfc', 'max:254'],
            'locale' => ['bail', 'required', 'string', 'in:pt-BR,en,es'],
            'subject' => ['bail', 'required', 'string', 'max:160'],
            'message' => ['bail', 'required', 'string', 'max:5000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = ['_token', 'name', 'email', 'locale', 'subject', 'message'];
            $unknown = array_diff(array_keys($this->all()), $allowed);

            if ($unknown !== []) {
                $validator->errors()->add('form', __('institutional.support.validation.unexpected'));
            }
        }];
    }
}
