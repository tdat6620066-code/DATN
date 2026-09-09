<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait CreatesReceiptImages
{
    protected function setUpCreatesReceiptImages(): void
    {
        Storage::fake('local');
    }

    protected function receiptImage(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    }
}
