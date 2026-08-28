<?php

namespace Tests\Feature;

use App\Livewire\Help;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('help'))->assertRedirect(route('login'));
    }

    public function test_a_signed_in_user_can_open_the_help_center(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('help'))->assertOk();
    }

    public function test_only_published_articles_appear_in_the_tree(): void
    {
        $user = User::factory()->create();
        $category = HelpCategory::create(['name' => 'Board']);
        HelpArticle::create(['title' => 'Sichtbar', 'help_category_id' => $category->id, 'is_published' => true]);
        HelpArticle::create(['title' => 'Entwurf', 'help_category_id' => $category->id, 'is_published' => false]);

        $tree = Livewire::actingAs($user)->test(Help::class)->instance()->tree();

        $titles = $tree->first()->articles->pluck('title');
        $this->assertContains('Sichtbar', $titles);
        $this->assertNotContains('Entwurf', $titles);
    }

    public function test_opening_a_published_article_via_the_route_selects_it(): void
    {
        $user = User::factory()->create();
        $article = HelpArticle::create(['title' => 'Titel', 'content' => 'Text', 'is_published' => true]);

        Livewire::actingAs($user)->test(Help::class, ['article' => $article->id])
            ->assertSet('selectedArticleId', $article->id);
    }

    public function test_an_unpublished_article_id_is_silently_ignored(): void
    {
        $user = User::factory()->create();
        $draft = HelpArticle::create(['title' => 'Entwurf', 'is_published' => false]);

        Livewire::actingAs($user)->test(Help::class, ['article' => $draft->id])
            ->assertSet('selectedArticleId', null);
    }

    public function test_a_foreign_or_missing_article_id_is_silently_ignored(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Help::class, ['article' => 999999])
            ->assertSet('selectedArticleId', null);
    }

    public function test_content_html_renders_markdown_for_the_selected_article(): void
    {
        $user = User::factory()->create();
        $article = HelpArticle::create([
            'title' => 'Mit Tabelle', 'is_published' => true,
            'content' => "| A | B |\n| --- | --- |\n| 1 | 2 |",
        ]);

        $html = Livewire::actingAs($user)->test(Help::class, ['article' => $article->id])
            ->instance()->contentHtml();

        $this->assertStringContainsString('<table>', $html);
    }

    public function test_a_no_click_creates_a_feedback_request_tied_to_the_article(): void
    {
        $user = User::factory()->create();
        $article = HelpArticle::create(['title' => 'Wochenübersicht', 'is_published' => true]);

        Livewire::actingAs($user)->test(Help::class, ['article' => $article->id])
            ->call('openFollowup')
            ->set('followupNote', 'Habe die Filteroption nicht gefunden.')
            ->call('sendFollowupFeedback');

        $request = SupportRequest::sole();
        $this->assertSame($user->id, $request->user_id);
        $this->assertSame('feedback', $request->type);
        $this->assertSame('Feedback zu: Wochenübersicht', $request->subject);
        $this->assertSame('Habe die Filteroption nicht gefunden.', $request->message);
        $this->assertSame('open', $request->status);
    }

    public function test_a_no_click_with_no_comment_still_creates_a_request(): void
    {
        $user = User::factory()->create();
        $article = HelpArticle::create(['title' => 'Titel', 'is_published' => true]);

        Livewire::actingAs($user)->test(Help::class, ['article' => $article->id])
            ->call('openFollowup')
            ->call('sendFollowupFeedback');

        $this->assertSame(1, SupportRequest::count());
    }
}
