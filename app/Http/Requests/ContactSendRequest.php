<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactSendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // всем разрешаем отправлять
    }

    public function rules(): array
    {
        return [
            'firstName' => 'required|string|max:255',
            'lastName'  => 'required|string|max:255',
            'email'     => 'required|email',
            'message'   => 'required|string|min:10',
        ];
    }

    public function messages(): array
    {
        return [
            'firstName.required' => 'First name is required',
            'lastName.required'  => 'Last name is required',
            'email.required'     => 'Email is required',
            'email.email'        => 'Please enter a valid email address',
            'message.required'   => 'Message is required',
            'message.min'        => 'Message must be at least 10 characters',
        ];
    }
}