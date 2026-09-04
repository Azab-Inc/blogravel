<?php

namespace App\Filament\Resources\MediaResource\Pages;

use App\Filament\Resources\MediaResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateMedia extends CreateRecord
{
    protected static string $resource = MediaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $file = $data['file'];

        $path = Storage::disk('public')->putFile('media', $file);

        $data['file_path'] = $path;
        $data['url'] = Storage::disk('public')->url($path);
        $data['mime_type'] = $file->getMimeType();
        $data['size'] = $file->getSize();
        $data['tenant_id'] = auth()->user()->tenant_id;

        unset($data['file']);

        return $data;
    }
}
