<?php

namespace Tests\Feature;

use App\Livewire\ArticleShow;
use App\Livewire\Library;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('library'))->assertRedirect(route('login'));
    }

    public function test_a_signed_in_user_sees_only_published_articles(): void
    {
        $user = User::factory()->create();
        $published = Article::create(['title' => 'Sichtbar', 'type' => 'blog', 'is_published' => true, 'published_at' => now()]);
        Article::create(['title' => 'Entwurf', 'type' => 'doc', 'is_published' => false]);

        $titles = Livewire::actingAs($user)->test(Library::class)->instance()->articles()->pluck('title');

        $this->assertContains('Sichtbar', $titles);
        $this->assertNotContains('Entwurf', $titles);
        $this->assertCount(1, $titles);
        $this->assertTrue($published->is_published);
    }

    public function test_search_matches_title_and_content(): void
    {
        $user = User::factory()->create();
        Article::create(['title' => 'Der neue Zeitplan', 'type' => 'doc', 'content' => 'Alltag', 'is_published' => true, 'published_at' => now()]);
        Article::create(['title' => 'Über die Community', 'type' => 'blog', 'content' => 'Enthält das Wort Zeitplan irgendwo.', 'is_published' => true, 'published_at' => now()]);
        Article::create(['title' => 'Unrelated', 'type' => 'doc', 'content' => 'Nichts davon.', 'is_published' => true, 'published_at' => now()]);

        $titles = Livewire::actingAs($user)->test(Library::class)
            ->set('search', 'zeitplan')
            ->instance()
            ->articles()
            ->pluck('title');

        $this->assertCount(2, $titles);
        $this->assertNotContains('Unrelated', $titles);
    }

    public function test_type_filter_toggles_on_a_second_click(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Library::class)
            ->call('setTypeFilter', 'blog')
            ->assertSet('typeFilter', 'blog')
            ->call('setTypeFilter', 'blog')
            ->assertSet('typeFilter', null);
    }

    public function test_type_filter_only_shows_that_type(): void
    {
        $user = User::factory()->create();
        Article::create(['title' => 'Ein Blogpost', 'type' => 'blog', 'is_published' => true, 'published_at' => now()]);
        Article::create(['title' => 'Ein Doc', 'type' => 'doc', 'is_published' => true, 'published_at' => now()]);

        $titles = Livewire::actingAs($user)->test(Library::class)
            ->call('setTypeFilter', 'blog')
            ->instance()
            ->articles()
            ->pluck('title');

        $this->assertSame(['Ein Blogpost'], $titles->all());
    }

    public function test_a_signed_in_user_cannot_open_an_unpublished_article(): void
    {
        $user = User::factory()->create();
        $draft = Article::create(['title' => 'Entwurf', 'type' => 'doc', 'is_published' => false]);

        $this->actingAs($user)->get(route('library.show', $draft))->assertNotFound();
    }

    public function test_an_admin_can_preview_an_unpublished_article(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $draft = Article::create(['title' => 'Entwurf', 'type' => 'doc', 'content' => 'Vorschau-Text', 'is_published' => false]);

        $this->actingAs($admin)->get(route('library.show', $draft))->assertOk()->assertSee('Vorschau-Text');
    }

    public function test_a_nonexistent_article_404s(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/app/library/999999')->assertNotFound();
    }

    public function test_a_published_article_renders_its_markdown_content(): void
    {
        $user = User::factory()->create();
        $article = Article::create([
            'title' => 'Mit Tabelle', 'type' => 'doc', 'is_published' => true, 'published_at' => now(),
            'content' => "| A | B |\n| --- | --- |\n| 1 | 2 |",
        ]);

        $html = Livewire::actingAs($user)->test(ArticleShow::class, ['article' => $article])
            ->instance()
            ->contentHtml();

        $this->assertStringContainsString('<table>', $html);
    }

    public function test_the_ephemeral_checkbox_hint_only_shows_when_a_checklist_is_present(): void
    {
        $user = User::factory()->create();
        $withChecklist = Article::create([
            'title' => 'Mit Checkliste', 'type' => 'guideline', 'is_published' => true, 'published_at' => now(),
            'content' => '- [ ] Schritt 1',
        ]);
        $withoutChecklist = Article::create([
            'title' => 'Ohne Checkliste', 'type' => 'guideline', 'is_published' => true, 'published_at' => now(),
            'content' => 'Nur Text.',
        ]);

        $this->actingAs($user)->get(route('library.show', $withChecklist))
            ->assertSee('werden nicht gespeichert');
        $this->actingAs($user)->get(route('library.show', $withoutChecklist))
            ->assertDontSee('werden nicht gespeichert');
    }
}
