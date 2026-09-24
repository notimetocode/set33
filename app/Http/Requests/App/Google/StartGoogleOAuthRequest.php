<?php

namespace App\Http\Requests\App\Google;

use Illuminate\Foundation\Http\FormRequest;

class StartGoogleOAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'return_site_id' => ['nullable', 'integer', 'exists:sites,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $siteId = $this->input('return_site_id');

            if ($siteId === null) {
                return;
            }

            $owns = $this->user()
                ?->sites()
                ->whereKey($siteId)
                ->exists();

            if (! $owns) {
                $validator->errors()->add('return_site_id', 'Сайт не найден.');
            }
        });
    }
}
