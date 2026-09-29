<?php

namespace App\Actions\Site;

use App\Models\Site;
use App\Models\SiteDocument;
use Illuminate\Http\UploadedFile;

class CreateSiteDocument
{
    public function __construct(private ReadMarkdownUpload $readMarkdown) {}

    /**
     * @param  array{
     *     title: string,
     *     description?: string|null,
     *     document: UploadedFile
     * }  $data
     */
    public function handle(Site $site, array $data): SiteDocument
    {
        $file = $data['document'];

        return $site->documents()->create([
            'title' => $data['title'],
            'description' => filled($data['description'] ?? null) ? $data['description'] : null,
            'content' => $this->readMarkdown->handle($file),
            'original_filename' => $file->getClientOriginalName(),
        ]);
    }
}
