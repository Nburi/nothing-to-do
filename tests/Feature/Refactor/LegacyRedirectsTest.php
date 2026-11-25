<?php

namespace Tests\Feature\Refactor;

use App\Mcp\McpServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Step 5.4 (switch preparation): the old week screens are retired without losing a link. Every
 * old URL, route name and bookmark still lands somewhere useful; nothing is deleted.
 */
class LegacyRedirectsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string, string}> old URL, route name, new URL */
    public static function retired(): array
    {
        return [
            'Zeitplan' => ['/app/schedule', 'schedule', '/board/week'],
            'Wochenplan' => ['/app/weekplan', 'weekplan', '/board/week/plan'],
            'Wochenrückblick' => ['/app/review', 'review', '/board/review'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('retired')]
    public function test_the_old_url_redirects_to_its_new_screen(string $old, string $name, string $new): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get($old)->assertRedirect($new)->assertStatus(302);
        $this->assertSame($old, route($name, absolute: false), 'the route name keeps working for old links and landing pages');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('retired')]
    public function test_a_guest_is_sent_to_the_login_not_into_the_app(string $old, string $name, string $new): void
    {
        $this->get($old)->assertRedirect(route('login'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('retired')]
    public function test_the_new_screen_opens_for_a_user_following_the_old_link(string $old, string $name, string $new): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        $this->actingAs($user)->followingRedirects()->get($old)->assertOk();
    }

    public function test_an_old_deep_link_with_a_query_string_still_arrives(): void
    {
        $user = User::factory()->create();

        // The Zeitplan badge used to point at /app/schedule?event=ID; the new week view has no such
        // highlight, but the link must not break.
        $this->actingAs($user)->get('/app/schedule?event=42')->assertRedirect('/board/week');
    }

    public function test_a_chosen_default_page_of_a_retired_screen_still_lands_in_the_new_app(): void
    {
        $user = User::factory()->create(['default_page' => 'weekplan', 'onboarding_completed_at' => now()]);

        $this->actingAs($user)->get('/')->assertRedirect(route('weekplan'));
        $this->actingAs($user)->followingRedirects()->get('/')->assertOk();
    }

    public function test_the_shell_no_longer_links_the_retired_screens(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        foreach (['/board', '/board/projects', '/board/ideas', '/board/week', '/board/review'] as $page) {
            $html = $this->actingAs($user)->get($page)->assertOk()->getContent();
            foreach (['/app/schedule', '/app/weekplan', '/app/review'] as $old) {
                $this->assertStringNotContainsString('href="'.url($old).'"', $html, "{$page} links {$old}");
            }
        }
    }

    public function test_the_mcp_week_review_tool_is_still_offered(): void
    {
        $names = array_column(app(McpServer::class)->allToolDefinitions(), 'name');

        $this->assertContains('get_week_review', $names);
    }
}
