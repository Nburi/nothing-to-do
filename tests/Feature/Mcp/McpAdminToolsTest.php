<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\Exceptions\McpUnknownToolException;
use App\Mcp\McpAbility;
use App\Mcp\McpServer;
use App\Models\FeatureAnnouncement;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class McpAdminToolsTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_TOOLS = [
        'list_support_requests', 'get_support_request', 'answer_support_request',
        'list_help_categories', 'create_help_category', 'update_help_category', 'delete_help_category', 'list_help_articles', 'get_help_article',
        'create_help_article', 'update_help_article', 'delete_help_article',
        'list_announcements', 'create_announcement', 'update_announcement', 'delete_announcement',
    ];

    private McpServer $server;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = app(McpServer::class);
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    /** @param  list<string>  $abilities */
    private function can(array $abilities = ['*']): callable
    {
        return fn (string $a) => in_array('*', $abilities, true) || in_array($a, $abilities, true);
    }

    private function tool(string $tool, array $args = [], ?User $as = null, ?callable $can = null): array
    {
        return $this->server->call($as ?? $this->admin, $can ?? $this->can(), $tool, $args);
    }

    // ── Gating ──────────────────────────────────────────────────────────

    public function test_admin_tools_exist_only_for_admins(): void
    {
        $regular = User::factory()->create();

        $adminNames = collect($this->server->availableTools($this->admin, $this->can()))->map->name();
        $regularNames = collect($this->server->availableTools($regular, $this->can()))->map->name();

        foreach (self::ADMIN_TOOLS as $tool) {
            $this->assertContains($tool, $adminNames);
            $this->assertNotContains($tool, $regularNames);
        }
    }

    public function test_a_non_admin_calling_an_admin_tool_gets_the_generic_unknown_tool_error(): void
    {
        $regular = User::factory()->create();

        $this->expectException(McpUnknownToolException::class);
        $this->expectExceptionMessage('Unknown tool: list_support_requests');

        $this->tool('list_support_requests', [], $regular);
    }

    public function test_admin_tools_still_respect_token_abilities(): void
    {
        $read = $this->can([McpAbility::READ]);
        $readWrite = $this->can([McpAbility::READ, McpAbility::WRITE]);

        $readNames = collect($this->server->availableTools($this->admin, $read))->map->name();
        $this->assertContains('list_support_requests', $readNames);
        $this->assertNotContains('answer_support_request', $readNames);
        $this->assertNotContains('create_help_article', $readNames);

        $rwNames = collect($this->server->availableTools($this->admin, $readWrite))->map->name();
        $this->assertContains('create_announcement', $rwNames);
        $this->assertNotContains('delete_announcement', $rwNames);
        $this->assertNotContains('delete_help_article', $rwNames);
    }

    // ── Support requests ────────────────────────────────────────────────

    public function test_support_requests_can_be_listed_filtered_and_read(): void
    {
        $submitter = User::factory()->create(['name' => 'Mia']);
        $open = SupportRequest::create(['user_id' => $submitter->id, 'type' => 'support', 'subject' => 'Login', 'message' => 'Geht nicht', 'status' => 'open']);
        SupportRequest::create(['user_id' => $submitter->id, 'type' => 'feedback', 'subject' => 'Idee', 'message' => 'Mehr Farben', 'status' => 'resolved']);

        $all = $this->tool('list_support_requests');
        $this->assertCount(2, $all['requests']);

        $filtered = $this->tool('list_support_requests', ['status' => 'open']);
        $this->assertCount(1, $filtered['requests']);
        $this->assertSame($open->id, $filtered['requests'][0]['id']);
        $this->assertSame('Mia', $filtered['requests'][0]['from']);

        $full = $this->tool('get_support_request', ['id' => $open->id]);
        $this->assertSame('Geht nicht', $full['message']);
        $this->assertNull($full['response']);
    }

    public function test_answering_saves_the_response_and_status_and_attributes_the_admin(): void
    {
        $request = SupportRequest::create(['user_id' => User::factory()->create()->id, 'type' => 'support', 'subject' => 'S', 'message' => 'M']);

        $result = $this->tool('answer_support_request', ['id' => $request->id, 'response' => '  Erledigt, danke!  ', 'status' => 'resolved']);

        $this->assertSame('resolved', $result['status']);
        $request->refresh();
        $this->assertSame('Erledigt, danke!', $request->response);
        $this->assertSame('resolved', $request->status);
        $this->assertSame($this->admin->id, $request->responded_by);
    }

    public function test_status_can_be_changed_without_a_response(): void
    {
        $request = SupportRequest::create(['user_id' => User::factory()->create()->id, 'type' => 'support', 'subject' => 'S', 'message' => 'M']);

        $this->tool('answer_support_request', ['id' => $request->id, 'status' => 'in_progress']);

        $request->refresh();
        $this->assertSame('in_progress', $request->status);
        $this->assertNull($request->response);
    }

    public function test_answering_needs_a_response_or_a_status_and_a_valid_status(): void
    {
        $request = SupportRequest::create(['user_id' => User::factory()->create()->id, 'type' => 'support', 'subject' => 'S', 'message' => 'M']);

        try {
            $this->tool('answer_support_request', ['id' => $request->id]);
            $this->fail('expected an error');
        } catch (McpToolExecutionException) {
            $this->assertTrue(true);
        }

        $this->expectException(ValidationException::class);
        $this->tool('answer_support_request', ['id' => $request->id, 'status' => 'bogus']);
    }

    // ── Help articles ───────────────────────────────────────────────────

    public function test_created_articles_are_always_unpublished_drafts(): void
    {
        $category = HelpCategory::create(['name' => 'Start', 'sort_order' => 0]);

        $result = $this->tool('create_help_article', [
            'title' => 'So startest du',
            'content' => '# Hallo',
            'category_id' => $category->id,
            'is_published' => true, // must be ignored
        ]);

        $this->assertFalse($result['is_published']);
        $this->assertSame('so-startest-du', $result['slug']);

        $article = HelpArticle::findOrFail($result['id']);
        $this->assertFalse($article->is_published);
        $this->assertNull($article->published_at);
        $this->assertSame($this->admin->id, $article->created_by);
        $this->assertSame($category->id, $article->help_category_id);
    }

    public function test_a_draft_article_can_be_edited_and_its_slug_follows_the_title(): void
    {
        $id = $this->tool('create_help_article', ['title' => 'Alt'])['id'];

        $this->tool('update_help_article', ['id' => $id, 'title' => 'Neuer Titel', 'content' => 'Text']);

        $article = HelpArticle::findOrFail($id);
        $this->assertSame('Neuer Titel', $article->title);
        $this->assertSame('neuer-titel', $article->slug);
        $this->assertSame('Text', $article->content);
        $this->assertFalse($article->is_published);
    }

    public function test_a_published_article_cannot_be_edited_or_deleted(): void
    {
        $article = HelpArticle::create(['title' => 'Live', 'slug' => 'live', 'content' => 'x', 'is_published' => true, 'published_at' => now()]);

        foreach ([
            ['update_help_article', ['id' => $article->id, 'content' => 'changed']],
            ['delete_help_article', ['id' => $article->id, 'confirm_title' => 'Live']],
        ] as [$tool, $args]) {
            try {
                $this->tool($tool, $args);
                $this->fail("{$tool} should refuse a published article");
            } catch (McpToolExecutionException $e) {
                $this->assertStringContainsString('already published', $e->getMessage());
            }
        }

        $article->refresh();
        $this->assertSame('x', $article->content);
        $this->assertTrue($article->is_published);
    }

    public function test_deleting_a_draft_article_requires_the_matching_title(): void
    {
        $id = $this->tool('create_help_article', ['title' => 'Weg damit'])['id'];

        try {
            $this->tool('delete_help_article', ['id' => $id, 'confirm_title' => 'falsch']);
            $this->fail('expected a mismatch error');
        } catch (McpToolExecutionException $e) {
            $this->assertStringContainsString('Weg damit', $e->getMessage());
        }
        $this->assertNotNull(HelpArticle::find($id));

        $this->assertTrue($this->tool('delete_help_article', ['id' => $id, 'confirm_title' => 'Weg damit'])['deleted']);
        $this->assertNull(HelpArticle::find($id));
    }

    public function test_articles_can_be_listed_with_drafts_searched_and_read(): void
    {
        $draft = $this->tool('create_help_article', ['title' => 'Entwurf', 'content' => 'Pomodoro erklärt'])['id'];
        HelpArticle::create(['title' => 'Live', 'slug' => 'live', 'content' => 'anderes', 'is_published' => true, 'published_at' => now()]);

        $this->assertCount(2, $this->tool('list_help_articles')['articles']);
        $this->assertCount(1, $this->tool('list_help_articles', ['only_drafts' => true])['articles']);
        $this->assertSame($draft, $this->tool('list_help_articles', ['search' => 'Pomodoro'])['articles'][0]['id']);
        $this->assertSame('Pomodoro erklärt', $this->tool('get_help_article', ['id' => $draft])['content']);
        $this->assertArrayHasKey('categories', $this->tool('list_help_categories'));
    }

    // ── Announcements ───────────────────────────────────────────────────

    public function test_created_announcements_are_always_unpublished_drafts(): void
    {
        $result = $this->tool('create_announcement', [
            'title' => 'Neu: Planer',
            'description' => 'Plane deine Tage.',
            'type' => 'release',
            'related_module' => 'planner',
            'only_for_module_users' => true,
            'is_published' => true, // must be ignored
        ]);

        $a = FeatureAnnouncement::findOrFail($result['id']);
        $this->assertFalse($a->is_published);
        $this->assertNull($a->published_at);
        $this->assertSame('release', $a->type);
        $this->assertSame('planner', $a->related_module);
        $this->assertTrue($a->only_for_module_users);
        $this->assertSame($this->admin->id, $a->created_by);
    }

    public function test_announcement_links_are_mutually_exclusive_on_update(): void
    {
        $id = $this->tool('create_announcement', [
            'title' => 'T', 'description' => 'D',
            'related_module' => 'agenda', 'highlight_selector' => '#x',
        ])['id'];

        $this->tool('update_announcement', ['id' => $id, 'link_type' => 'external', 'external_url' => 'https://example.com', 'external_link_label' => 'Lies mehr']);

        $a = FeatureAnnouncement::findOrFail($id);
        $this->assertNull($a->related_module);
        $this->assertNull($a->highlight_selector);
        $this->assertSame('https://example.com', $a->external_url);
        $this->assertSame('Lies mehr', $a->external_link_label);

        $this->tool('update_announcement', ['id' => $id, 'link_type' => 'none', 'title' => 'Neu']);

        $a->refresh();
        $this->assertNull($a->external_url);
        $this->assertSame('Neu', $a->title);
    }

    public function test_announcement_validation_rejects_bad_input(): void
    {
        $this->expectException(ValidationException::class);
        $this->tool('create_announcement', ['title' => 'T', 'description' => str_repeat('x', 501)]);
    }

    public function test_a_published_announcement_cannot_be_edited_or_deleted(): void
    {
        $a = FeatureAnnouncement::create(['title' => 'Live', 'description' => 'd', 'type' => 'info', 'is_published' => true, 'published_at' => now()]);

        foreach ([
            ['update_announcement', ['id' => $a->id, 'title' => 'Changed']],
            ['delete_announcement', ['id' => $a->id, 'confirm_title' => 'Live']],
        ] as [$tool, $args]) {
            try {
                $this->tool($tool, $args);
                $this->fail("{$tool} should refuse a published announcement");
            } catch (McpToolExecutionException $e) {
                $this->assertStringContainsString('already published', $e->getMessage());
            }
        }

        $this->assertSame('Live', $a->fresh()->title);
    }

    public function test_deleting_a_draft_announcement_requires_the_matching_title(): void
    {
        $id = $this->tool('create_announcement', ['title' => 'Weg', 'description' => 'd'])['id'];

        try {
            $this->tool('delete_announcement', ['id' => $id, 'confirm_title' => 'nope']);
            $this->fail('expected a mismatch error');
        } catch (McpToolExecutionException) {
            $this->assertNotNull(FeatureAnnouncement::find($id));
        }

        $this->tool('delete_announcement', ['id' => $id, 'confirm_title' => 'Weg']);
        $this->assertNull(FeatureAnnouncement::find($id));
        $this->assertCount(0, $this->tool('list_announcements')['announcements']);
    }

    // -- Help categories ---------------------------------------------------

    public function test_categories_can_be_created_renamed_moved_and_deleted(): void
    {
        $root = $this->tool('create_help_category', ['name' => 'Start'])['id'];
        $other = $this->tool('create_help_category', ['name' => 'Mehr'])['id'];
        $sub = $this->tool('create_help_category', ['name' => 'Basics', 'parent_id' => $root])['id'];

        $this->assertSame($root, HelpCategory::findOrFail($sub)->parent_id);

        $this->tool('update_help_category', ['id' => $sub, 'name' => 'Grundlagen', 'parent_id' => $other]);
        $moved = HelpCategory::findOrFail($sub);
        $this->assertSame('Grundlagen', $moved->name);
        $this->assertSame($other, $moved->parent_id);

        $draft = $this->tool('create_help_article', ['title' => 'Entwurf', 'category_id' => $sub])['id'];

        try {
            $this->tool('delete_help_category', ['id' => $sub, 'confirm_name' => 'falsch']);
            $this->fail('expected a mismatch error');
        } catch (McpToolExecutionException) {
            $this->assertNotNull(HelpCategory::find($sub));
        }

        $this->assertTrue($this->tool('delete_help_category', ['id' => $sub, 'confirm_name' => 'Grundlagen'])['deleted']);
        $this->assertNull(HelpCategory::find($sub));
        $this->assertNull(HelpArticle::findOrFail($draft)->help_category_id);
    }

    public function test_category_nesting_is_limited_to_two_levels(): void
    {
        $root = $this->tool('create_help_category', ['name' => 'A'])['id'];
        $sub = $this->tool('create_help_category', ['name' => 'B', 'parent_id' => $root])['id'];
        $other = $this->tool('create_help_category', ['name' => 'C'])['id'];

        foreach ([
            ['create_help_category', ['name' => 'D', 'parent_id' => $sub]],
            ['update_help_category', ['id' => $root, 'parent_id' => $other]], // has children
            ['update_help_category', ['id' => $other, 'parent_id' => $other]],
        ] as [$tool, $args]) {
            try {
                $this->tool($tool, $args);
                $this->fail("{$tool} should refuse");
            } catch (McpToolExecutionException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_a_category_with_published_articles_cannot_be_changed_or_deleted(): void
    {
        $category = HelpCategory::create(['name' => 'Live', 'sort_order' => 0]);
        HelpArticle::create(['title' => 'A', 'slug' => 'a', 'help_category_id' => $category->id, 'is_published' => true, 'published_at' => now()]);

        foreach ([
            ['update_help_category', ['id' => $category->id, 'name' => 'Neu']],
            ['delete_help_category', ['id' => $category->id, 'confirm_name' => 'Live']],
        ] as [$tool, $args]) {
            try {
                $this->tool($tool, $args);
                $this->fail("{$tool} should refuse");
            } catch (McpToolExecutionException $e) {
                $this->assertStringContainsString('published articles', $e->getMessage());
            }
        }

        $this->assertSame('Live', $category->fresh()->name);
    }
}
