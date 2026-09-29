<?php

namespace App\Http\Resources\App;

use App\Models\SiteDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SiteDocument
 */
class SiteDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'original_filename' => $this->original_filename,
            'content_preview' => $this->contentPreview(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function contentPreview(): string
    {
        $content = (string) $this->content;

        if ($content === '') {
            return '';
        }

        return mb_strlen($content) > 280
            ? mb_substr($content, 0, 277).'...'
            : $content;
    }
}
