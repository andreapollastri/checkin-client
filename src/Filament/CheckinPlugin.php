<?php

namespace Checkin\Filament;

use Checkin\Checkin;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;

/**
 * Adds a "Continue with Checkin" button to a panel's login page.
 *
 *     ->plugin(CheckinPlugin::make()->before())
 */
class CheckinPlugin implements Plugin
{
    protected string|Closure|null $label = null;

    protected string|Closure|null $position = null;

    protected string|Closure|null $color = null;

    protected string|Closure|null $icon = null;

    protected bool|Closure|null $divider = null;

    protected string|Closure|null $dividerLabel = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'checkin';
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook(
            $this->getPosition() === 'before' ? PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE : PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
            fn (): ?View => $this->renderButton(),
        );
    }

    public function boot(Panel $panel): void {}

    public function label(string|Closure $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function before(): static
    {
        $this->position = 'before';

        return $this;
    }

    public function after(): static
    {
        $this->position = 'after';

        return $this;
    }

    public function color(string|Closure $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function icon(string|Closure|null $icon): static
    {
        $this->icon = $icon ?? '';

        return $this;
    }

    public function divider(bool|Closure $divider = true, ?string $label = null): static
    {
        $this->divider = $divider;

        if ($label !== null) {
            $this->dividerLabel = $label;
        }

        return $this;
    }

    public function getLabel(): string
    {
        return $this->evaluate($this->label) ?? config('checkin.filament.label') ?? app(Checkin::class)->label();
    }

    public function getPosition(): string
    {
        return $this->evaluate($this->position) ?? config('checkin.filament.position', 'after');
    }

    public function getColor(): string
    {
        return $this->evaluate($this->color) ?? config('checkin.filament.color', 'gray');
    }

    public function getIcon(): ?string
    {
        $icon = $this->evaluate($this->icon) ?? config('checkin.filament.icon', 'heroicon-o-key');

        return $icon === '' ? null : $icon;
    }

    public function hasDivider(): bool
    {
        return (bool) ($this->evaluate($this->divider) ?? config('checkin.filament.divider', true));
    }

    public function getDividerLabel(): string
    {
        return $this->evaluate($this->dividerLabel) ?? config('checkin.filament.divider_label', 'or');
    }

    protected function renderButton(): ?View
    {
        $checkin = app(Checkin::class);

        if (! $checkin->isEnabled()) {
            return null;
        }

        return view('checkin::filament.login-button', [
            'url' => $checkin->redirectUrl(),
            'label' => $this->getLabel(),
            'position' => $this->getPosition(),
            'color' => $this->getColor(),
            'icon' => $this->getIcon(),
            'divider' => $this->hasDivider(),
            'dividerLabel' => $this->getDividerLabel(),
        ]);
    }

    protected function evaluate(mixed $value): mixed
    {
        return $value instanceof Closure ? $value() : $value;
    }
}
