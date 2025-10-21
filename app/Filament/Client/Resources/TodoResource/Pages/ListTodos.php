<?php

namespace App\Filament\Client\Resources\TodoResource\Pages;

use App\Filament\Client\Resources\TodoResource;
use App\Models\Todo;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListTodos extends ListRecords
{
    protected static string $resource = TodoResource::class;

    protected static ?string $title = 'Notepad';

    protected static string $view = 'filament.client.resources.todo-resource.list';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getRecords()
    {
        return Filament::getTenant()
            ->todos()
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(12);
    }

    public function editRecord($id)
    {
        $record = Todo::find($id);

        return redirect()->to($this->getResource()::getUrl('edit', ['record' => $record]));
    }
}
