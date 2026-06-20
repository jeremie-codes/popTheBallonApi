<?php

namespace App\Filament\Resources\MessageBundles;

use App\Filament\Resources\MessageBundles\Pages\CreateMessageBundle;
use App\Filament\Resources\MessageBundles\Pages\EditMessageBundle;
use App\Filament\Resources\MessageBundles\Pages\ListMessageBundles;
use App\Filament\Resources\MessageBundles\Pages\ViewMessageBundle;
use App\Filament\Resources\MessageBundles\Schemas\MessageBundleForm;
use App\Filament\Resources\MessageBundles\Schemas\MessageBundleInfolist;
use App\Filament\Resources\MessageBundles\Tables\MessageBundlesTable;
use App\Models\MessageBundle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MessageBundleResource extends Resource
{
    protected static ?string $model = MessageBundle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'App\Models\MessageBundle';

    public static function form(Schema $schema): Schema
    {
        return MessageBundleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MessageBundleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MessageBundlesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMessageBundles::route('/'),
            'create' => CreateMessageBundle::route('/create'),
            'view' => ViewMessageBundle::route('/{record}'),
            'edit' => EditMessageBundle::route('/{record}/edit'),
        ];
    }
}
