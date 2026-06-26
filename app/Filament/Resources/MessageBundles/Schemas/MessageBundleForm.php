<?php

namespace App\Filament\Resources\MessageBundles\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MessageBundleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                TextInput::make('messages')
                    ->required()
                    ->numeric(),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('currency')
                    ->required()
                    ->default('USD'),
                TextInput::make('equivalent')
                    ->required()
                    ->numeric()
                    ->prefix('CDF'),
                Textarea::make('description')
                    ->default(null)
                    ->columnSpanFull(),
                Toggle::make('popular')
                    ->required(),
                Toggle::make('active')
                    ->required(),
            ]);
    }
}
