<a href="{{ $url }}" {{ $attributes->merge(['class' => 'checkin-button']) }} data-checkin-button>
    @if ($icon)
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" width="1.1em" height="1.1em" aria-hidden="true" style="vertical-align: -0.15em; margin-inline-end: 0.5em;">
            <path d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.03 5.91l-2.95 2.95a1.5 1.5 0 0 1-1.06.44H9v1.5a1.5 1.5 0 0 1-1.5 1.5H6v1.5a1.5 1.5 0 0 1-1.5 1.5H3a1.5 1.5 0 0 1-1.5-1.5v-2.38c0-.4.16-.78.44-1.06l6.9-6.9A6 6 0 1 1 21.75 8.25Z" />
        </svg>
    @endif
    <span>{{ $label }}</span>
</a>
