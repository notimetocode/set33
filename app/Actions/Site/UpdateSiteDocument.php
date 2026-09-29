<?php

namespace App\Actions\Site;

use App\Models\SiteDocument;
use Illuminate\Http\UploadedFile;

class UpdateSiteDocument
{
    public function __construct(private ReadMarkdownUpload $readMarkdown) {}

    /**
     * @param  array{
     *     title: string,
     *     description?: string|null,
     *     document?: UploadedFile|null
     * }  $data
     */
    public function handle(SiteDocument $document, array $data): SiteDocument
    {
        $attributes = [
            'title' => $data['title'],
            'description' => filled($data['description'] ?? null) ? $data['description'] : null,
        ];

        if (isset($data['document']) && $data['document'] instanceof UploadedFile) {
            $file = $data['document'];
            $attributes['content'] = $this->readMarkdown->handle($file);
            $attributes['original_filename'] = $file->getClientOriginalName();
        }

        $document->update($attributes);

        return $document->refresh();
    }
}
