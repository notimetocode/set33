<?php

namespace App\Http\Requests\App\Google;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteGoogleIntegrationRequest extends FormRequest
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
            'ga4_property_id' => ['nullable', 'string', 'max:128'],
            'gsc_site_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ga4_property_id.max' => 'Слишком длинный идентификатор GA4 property.',
            'gsc_site_url.max' => 'Слишком длинный URL сайта Search Console.',
        ];
    }
}
