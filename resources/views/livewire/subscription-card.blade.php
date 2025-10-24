<div class="hidden md:block">
    <a href="{{ $redirectUrl }}"
       @class([
            'flex items-center space-x-2 border px-4 py-2 shadow-md rounded-md group',
            'transition-all duration-200 ease-in-out', // Common transition effects
            'hover:shadow-lg hover:scale-105', // Subtle hover animation
            'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700' => !$primary, // Default style
            'bg-indigo-600 hover:bg-indigo-700 text-white border-transparent' => $primary, // Primary button style
       ])>
        
        @if($icon)
            {!! $icon !!}
        @endif

        <span @class([
            'px-2 text-sm font-medium tracking-wide',
            'text-gray-700 dark:text-gray-200' => !$primary,
            'text-white' => $primary
        ])>
            {{ $text }}
        </span>
    </a>
</div>