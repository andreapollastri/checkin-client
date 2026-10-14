<?php

namespace Checkin\Tests\Feature;

use Checkin\Tests\TestCase;
use Illuminate\Support\Facades\Blade;

class ButtonTest extends TestCase
{
    public function test_it_renders_a_link_to_the_redirect_route(): void
    {
        $html = Blade::render('<x-checkin-button class="btn" />');

        $this->assertStringContainsString('href="https://app.test/auth/checkin/redirect"', $html);
        $this->assertStringContainsString('class="checkin-button btn"', $html);
        $this->assertStringContainsString('Continue with Checkin', $html);
        $this->assertStringContainsString('<svg', $html);
    }

    public function test_label_and_icon_can_be_changed(): void
    {
        $html = Blade::render('<x-checkin-button label="Accedi con AP ID" :icon="false" />');

        $this->assertStringContainsString('Accedi con AP ID', $html);
        $this->assertStringNotContainsString('<svg', $html);
    }

    public function test_nothing_renders_when_disabled(): void
    {
        config()->set('checkin.enabled', false);

        $this->assertSame('', trim(Blade::render('<x-checkin-button />')));
    }
}
