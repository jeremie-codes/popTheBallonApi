<?php

namespace App\Filament\Resources\MessageBundles\Pages;

use App\Filament\Resources\MessageBundles\MessageBundleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMessageBundle extends CreateRecord
{
    protected static string $resource = MessageBundleResource::class;
}
