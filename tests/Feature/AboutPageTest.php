<?php

namespace Tests\Feature;

use Tests\TestCase;

class AboutPageTest extends TestCase
{
    public function test_public_about_page_is_available(): void
    {
        $this->get(route('about.index'))
            ->assertOk()
            ->assertSee('VỀ SMASHZONE')
            ->assertSee('Đặt sân dễ dàng');
    }
}
