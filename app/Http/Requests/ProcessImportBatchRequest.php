<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessImportBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isLeaderOrAdmin() ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'batch_id' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'offset' => ['required', 'integer', 'min:0'],
            'limit' => ['required', 'integer', 'min:1', 'max:20'],
            'fallback_password' => ['required', 'string', 'min:6', 'max:18'],
        ];
    }
}
