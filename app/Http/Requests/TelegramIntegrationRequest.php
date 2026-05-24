<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TelegramIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'bot_name' => ['nullable', 'string', 'max:255'],
            'bot_username' => ['nullable', 'string', 'max:255'],
            'bot_token' => ['required', 'string'],
            'chat_id' => ['nullable', 'string'],
            'parse_mode' => ['required', 'string', 'in:HTML,Markdown'],
            'is_enabled' => ['boolean'],
        ];
    }
}
