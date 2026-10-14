<?php

namespace Checkin\View\Components;

use Checkin\Checkin;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * <x-checkin-button /> — a link starting the Checkin sign-in.
 *
 * Pass your own classes: <x-checkin-button class="btn btn-outline-secondary w-100" />
 */
class Button extends Component
{
    public string $url;

    public string $label;

    public function __construct(
        protected Checkin $checkin,
        ?string $label = null,
        public bool $icon = true,
    ) {
        $this->url = $checkin->isEnabled() ? $checkin->redirectUrl() : '#';
        $this->label = $label ?? $checkin->label();
    }

    public function shouldRender(): bool
    {
        return $this->checkin->isEnabled();
    }

    public function render(): View
    {
        return view('checkin::components.button');
    }
}
