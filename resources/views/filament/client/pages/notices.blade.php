@php
    use Filament\Facades\Filament;
    use Illuminate\Support\Facades\Auth;

    $notices = Auth::user()->hasRole('Admin')
        ? Filament::getTenant()->notices()->where('is_active', true)->get()
        : Filament::getTenant()
            ->notices()
            ->where('is_active', true)
            ->where('name', '!=', 'Subscription Expiring Soon')
            ->get();

    $color = $notices->isEmpty() ? 'gray' : 'primary';
@endphp
<x-filament::icon-button :badge="count($notices) ?: null" color="gray" icon="heroicon-o-megaphone" icon-size="lg"
    class="fi-topbar-database-notifications-btn" x-on:click="$dispatch('open-modal', { id: 'notice-list-modal' })" />

<x-filament::modal id="notice-list-modal" width="2xl">
    <x-slot name="heading">Active Notices</x-slot>

    <div class="space-y-4 max-h-[70vh] overflow-y-auto overflow-x-hidden">
        @foreach ($notices as $notice)
            <div class="p-4 rounded-lg w-full"
                style="background-color: {{ $notice->content['BackgroundColor'] ?? '#D97706' }}; overflow-x:hidden;">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <x-filament::icon :icon="$notice->icon ?? 'heroicon-o-megaphone'"
                            style="color: {{ $notice->content['IconColor'] ?? '#FFFFFF' }}" class="h-5 w-5 shrink-0" />
                        <span class="truncate" style="color: {{ $notice->content['TextColor'] ?? '#FFFFFF' }}">
                            {!! $notice->name !!}
                        </span>
                    </div>

                    @php
                        $contentType = $notice->content['type'] ?? 'text';
                        $linkUrl = $notice->content['link_url'] ?? null;
                        $documentPath = $notice->content['document'] ?? null;
                    @endphp

                    <div class="flex-shrink-0">
                        @if ($contentType === 'link' && $linkUrl)
                            <x-filament::button color="primary" tag="a" href="{{ $linkUrl }}"
                                target="_blank">
                                Open Link
                            </x-filament::button>
                        @elseif ($contentType === 'file' && $documentPath)
                            <x-filament::button color="primary" tag="a" href="{{ Storage::url($documentPath) }}"
                                target="_blank">
                                View File
                            </x-filament::button>
                        @elseif (!empty($notice->content['body']))
                            <x-filament::button color="primary"
                                x-on:click="$dispatch('open-modal', { id: 'notice-content-{{ $notice->id }}' })">
                                Read
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-filament::modal>

@foreach ($notices as $notice)
    @if (!empty($notice->content['body']))
        <x-filament::modal id="notice-content-{{ $notice->id }}" width="2xl">
            <x-slot name="heading">{{ $notice->name ?? 'Notice' }}</x-slot>

            <div class="prose max-h-[60vh] overflow-y-auto overflow-x-hidden">
                {!! $notice->content['body'] !!}
            </div>

            <x-slot name="footer">
                <x-filament::button color="secondary"
                    x-on:click="$dispatch('close-modal', { id: 'notice-content-{{ $notice->id }}' })">
                    Close
                </x-filament::button>
            </x-slot>
        </x-filament::modal>
    @endif
@endforeach
