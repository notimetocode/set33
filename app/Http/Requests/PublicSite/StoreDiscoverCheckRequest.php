<?php

namespace App\Http\Requests\PublicSite;

use App\Services\Discover\DiscoverCheckCatalog;
use App\Services\Discover\PublicUrlGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDiscoverCheckRequest extends FormRequest
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

        $payload = [
            'url' => $url,
        ];

        $skip = $this->input('skip');

        if (is_string($skip)) {
            $ids = preg_split('/\s*,\s*/', $skip) ?: [];
            $payload['skip'] = array_values(array_filter(
                array_map(trim(...), $ids),
                fn (string $id): bool => $id !== '',
            ));
        }

        $this->merge($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'url', 'max:2048'],
            'skip' => ['sometimes', 'array'],
            'skip.*' => ['string', Rule::in(DiscoverCheckCatalog::ids())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.required' => __('public.discover_check.validation_url_required'),
            'url.url' => __('public.discover_check.validation_url_invalid'),
            'url.max' => __('public.discover_check.validation_url_invalid'),
            'skip.array' => __('public.discover_check.validation_skip_invalid'),
            'skip.*.string' => __('public.discover_check.validation_skip_invalid'),
            'skip.*.in' => __('public.discover_check.validation_skip_invalid'),
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

            if (! is_string($host) || $host === '' || PublicUrlGuard::isBlockedHost($host)) {
                $validator->errors()->add('url', __('public.discover_check.validation_url_blocked'));
            }
        });
    }
}
