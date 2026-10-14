<div class="fi-checkin-sso" style="display: flex; flex-direction: column; gap: 1rem; {{ $position === 'before' ? 'margin-bottom: 1.5rem;' : 'margin-top: 1.5rem;' }}">
    @if ($divider && $position === 'after')
        <div style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.6;">
            <span style="flex: 1; border-top: 1px solid currentColor; opacity: 0.3;"></span>
            <span>{{ $dividerLabel }}</span>
            <span style="flex: 1; border-top: 1px solid currentColor; opacity: 0.3;"></span>
        </div>
    @endif

    <x-filament::button
        tag="a"
        :href="$url"
        :color="$color"
        :icon="$icon"
        outlined
        size="lg"
        style="width: 100%; justify-content: center;"
        data-checkin-button
    >
        {{ $label }}
    </x-filament::button>

    @if ($divider && $position === 'before')
        <div style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.6;">
            <span style="flex: 1; border-top: 1px solid currentColor; opacity: 0.3;"></span>
            <span>{{ $dividerLabel }}</span>
            <span style="flex: 1; border-top: 1px solid currentColor; opacity: 0.3;"></span>
        </div>
    @endif
</div>
