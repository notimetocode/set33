<?php

namespace App\Http\Requests\PublicSite;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSiteAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $url = trim((string) $this->input('url', ''));

        if ($url !== '' && ! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $this->merge([
            'url' => $url,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'url', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.required' => __('public.site_audit.validation_url_required'),
            'url.url' => __('public.site_audit.validation_url_invalid'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $url = (string) $this->input('url');
            $host = parse_url($url, PHP_URL_HOST);

            if (! is_string($host) || $host === '') {
                $validator->errors()->add('url', __('public.site_audit.validation_url_invalid'));

                return;
            }

            $host = strtolower($host);

            if ($this->isBlockedHost($host)) {
                $validator->errors()->add('url', __('public.site_audit.validation_url_blocked'));
            }
        });
    }

    private function isBlockedHost(string $host): bool
    {
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            return true;
        }

        if (str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return false;
    }
}
