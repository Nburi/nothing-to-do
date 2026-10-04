<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Impressum & Datenschutz — static, guest-readable legal pages (no auth, no
 * Livewire needed). See resources/views/legal/*.blade.php and the shared
 * resources/views/components/legal-layout.blade.php shell.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_impressum_is_reachable_without_login(): void
    {
        $this->get(route('impressum'))
            ->assertOk()
            ->assertSee('Impressum')
            ->assertSee('Haftungsausschluss');
    }

    public function test_datenschutz_is_reachable_without_login(): void
    {
        $this->get(route('datenschutz'))
            ->assertOk()
            ->assertSee('Datenschutzerklärung')
            ->assertSee('Deine Rechte');
    }

    public function test_impressum_links_to_datenschutz_and_back(): void
    {
        $this->get(route('impressum'))
            ->assertSee(route('datenschutz'), false);
    }

    public function test_datenschutz_links_to_impressum(): void
    {
        $this->get(route('datenschutz'))
            ->assertSee(route('impressum'), false);
    }

    public function test_the_login_page_links_to_both_legal_pages(): void
    {
        $this->get(route('login'))
            ->assertSee(route('impressum'), false)
            ->assertSee(route('datenschutz'), false);
    }

    public function test_the_register_page_links_to_both_legal_pages(): void
    {
        $this->get(route('register'))
            ->assertSee(route('impressum'), false)
            ->assertSee(route('datenschutz'), false);
    }

    public function test_the_landing_page_links_to_both_legal_pages(): void
    {
        $this->get(route('home'))
            ->assertSee(route('impressum'), false)
            ->assertSee(route('datenschutz'), false);
    }

    public function test_both_pages_are_reachable_for_an_authenticated_user_too(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('impressum'))->assertOk();
        $this->actingAs($user)->get(route('datenschutz'))->assertOk();
    }
}
