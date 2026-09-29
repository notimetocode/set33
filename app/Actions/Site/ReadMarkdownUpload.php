<?php

namespace App\Actions\Site;

use Illuminate\Http\UploadedFile;

class ReadMarkdownUpload
{
    public function handle(UploadedFile $file): string
    {
        $raw = (string) file_get_contents($file->getRealPath());

        if ($raw !== '' && ! mb_check_encoding($raw, 'UTF-8')) {
            $converted = @mb_convert_encoding($raw, 'UTF-8', 'UTF-8, Windows-1251, ISO-8859-1');
            $raw = is_string($converted) ? $converted : $raw;
        }

        return str_replace("\0", '', $raw);
    }
}
