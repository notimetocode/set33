<?php

namespace App\Http\Requests\App\Github;

use Illuminate\Foundation\Http\FormRequest;

class ListGithubCommitsRequest extends FormRequest
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
            'sha' => ['sometimes', 'string', 'max:255'],
            'since' => ['sometimes', 'date'],
            'until' => ['sometimes', 'date', 'after_or_equal:since'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array{sha?: string, since?: string, until?: string, page?: int, per_page?: int}
     */
    public function filters(): array
    {
        $filters = [];

        if ($this->filled('sha')) {
            $filters['sha'] = (string) $this->validated('sha');
        }

        if ($this->filled('since')) {
            $filters['since'] = $this->date('since')->toIso8601String();
        }

        if ($this->filled('until')) {
            $filters['until'] = $this->date('until')->toIso8601String();
        }

        if ($this->filled('page')) {
            $filters['page'] = (int) $this->validated('page');
        }

        if ($this->filled('per_page')) {
            $filters['per_page'] = (int) $this->validated('per_page');
        }

        return $filters;
    }
}
