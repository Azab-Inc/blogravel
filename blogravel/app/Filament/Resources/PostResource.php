<?php

namespace App\Filament\Resources;

use App\Enums\PostStatus;
use App\Filament\Actions\GenerateAiPostAction;
use App\Filament\Resources\PostResource\Pages;
use App\Models\Post;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static UnitEnum|string|null $navigationGroup = 'Content';

    protected static ?string $modelLabel = 'Post';

    protected static ?string $pluralModelLabel = 'Posts';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->disabled()
                    ->dehydrated(false)
                    ->maxLength(255),
                Textarea::make('content')
                    ->rows(10)
                    ->required(),
                SchemaActions::make([
                    GenerateAiPostAction::make(),
                ]),
                Textarea::make('excerpt')
                    ->rows(3)
                    ->nullable(),
                Select::make('status')
                    ->options(PostStatus::class)
                    ->required(),
                DatePicker::make('published_at')
                    ->nullable(),
                CheckboxList::make('categories')
                    ->relationship('categories', 'name')
                    ->columns(3),
                CheckboxList::make('tags')
                    ->relationship('tags', 'name')
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('author.name')
                    ->label('Author')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (PostStatus $state) => $state->color()),
                TextColumn::make('categories.name')
                    ->badge(),
                TextColumn::make('published_at')
                    ->date()
                    ->sortable()
                    ->placeholder('Draft'),
                TextColumn::make('created_at')
                    ->date()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
