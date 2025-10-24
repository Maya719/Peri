<x-filament::page>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        @foreach ($this->getRecords() as $record)
            <div class="p-4 bg-white rounded-xl shadow">
                <div class="text-sm text-gray-600 mt-2">
                    {{ $record->description ?? 'No description' }}
                </div>

                <div class="mt-4 flex justify-end">
                    <x-filament::icon-button icon="heroicon-m-pencil-square" color="primary" size="sm"
                        wire:click="editRecord({{ $record->id }})" tooltip="Edit" label="Edit" />
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6">
        {{ $this->getRecords()->links() }}
    </div>
</x-filament::page>
