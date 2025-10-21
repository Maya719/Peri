<?php

namespace App\Filament\Client\Resources\TodoResource\Pages;

use App\Filament\Client\Resources\TodoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTodo extends EditRecord
{
    protected static string $resource = TodoResource::class;
}
