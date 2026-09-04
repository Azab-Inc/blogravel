<?php

namespace App\Filament\Pages;

use App\Jobs\WordPressImportJob;
use App\Services\WordPress\WxrParser;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use UnitEnum;

class ImportWordPress extends Page
{
    protected string $view = 'filament.pages.import-wordpress';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static UnitEnum|string|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Import WordPress';

    public ?array $file = null;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('file')
                    ->label('WordPress WXR File')
                    ->acceptedFileTypes(['text/xml', 'application/xml'])
                    ->rules(['required'])
                    ->directory('imports')
                    ->disk('local')
                    ->maxSize(102400)
                    ->previewable(false),
            ]);
    }

    public function import(): void
    {
        $data = $this->form->getState();

        $filePath = storage_path('app/'.$data['file']);
        if (! is_file($filePath)) {
            Notification::make()
                ->title('File not found')
                ->danger()
                ->send();

            return;
        }

        $parser = new WxrParser($filePath);
        $result = $parser->parse();

        $items = $result['items'];

        if (empty($items)) {
            Notification::make()
                ->title('No items found')
                ->body('The uploaded file did not contain any importable items.')
                ->warning()
                ->send();

            return;
        }

        $postCount = count(array_filter($items, fn (array $item): bool => ($item['type'] ?? '') === 'post'));
        $pageCount = count(array_filter($items, fn (array $item): bool => ($item['type'] ?? '') === 'page'));
        $attachmentCount = count(array_filter($items, fn (array $item): bool => ($item['type'] ?? '') === 'attachment'));

        WordPressImportJob::dispatch(
            $items,
            tenant()->id,
            auth()->id(),
        );

        Notification::make()
            ->title('Import started')
            ->body("Dispatched import job: {$postCount} posts, {$pageCount} pages, {$attachmentCount} attachments.")
            ->success()
            ->send();
    }
}
