<x-filament-panels::page>
    <style>
        fieldset legend {
            font-size: 1.15rem !important;
            font-weight: 600 !important;
        }

        .fi-fo-repeater-item-header {
            background-color: #17345c !important;
            border-radius: 11px 11px 0px 0px !important;
        }
    </style>
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}
        <x-filament-panels::form.actions :actions="$this->getCachedFormActions()" :full-width="$this->hasFullWidthFormActions()" />
    </x-filament-panels::form>
</x-filament-panels::page>