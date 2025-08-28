<?php

namespace App\Filament\Resources\Posts;

use App\Filament\Resources\Posts\Pages;
use App\Filament\Resources\Posts\Schemas\PostForm;
use App\Filament\Resources\Posts\Tables\PostsTable;
use App\Models\Post;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PostResource extends Resource
{
    protected static ?string $model = \App\Models\Post::class;

   
    protected static string|\UnitEnum|null  $navigationGroup = 'Content';
    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Blog posts';
    protected static ?string $pluralLabel = 'Posts';
    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return PostForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PostsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'view'   => Pages\ViewPost::route('/{record}'),
            'edit'   => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
