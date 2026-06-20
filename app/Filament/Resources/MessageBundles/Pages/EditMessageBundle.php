<?php

namespace App\Filament\Resources\MessageBundles\Pages;

use App\Filament\Resources\MessageBundles\MessageBundleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMessageBundle extends EditRecord
{
    protected static string $resource = MessageBundleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
