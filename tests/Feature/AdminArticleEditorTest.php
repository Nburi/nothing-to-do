<?php

namespace Tests\Feature;

use App\Livewire\Admin\ArticleEditor;
use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminArticleEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_admin_cannot_open_the_editor(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route('admin.library'))->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.library'))->assertRedirect(route('login'));
    }

    public function test_an_admin_can_open_the_editor(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.library'))->assertOk();
    }

    public function test_creating_an_article_opens_it_immediately_as_a_draft(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)->test(ArticleEditor::class)
            ->call('createArticle')
            ->assertSet('formType', Article::DEFAULT_TYPE);

        $article = Article::sole();
        $this->assertFalse($article->is_published);
        $this->assertSame($admin->id, $article->created_by);
    }

    public function test_editing_the_title_autosaves_immediately(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $article = Article::create(['title' => 'Alt', 'type' => 'doc', 'created_by' => $admin->id]);

        Livewire::actingAs($admin)->test(ArticleEditor::class)
            ->call('startEdit', $article->id)
            ->set('formTitle', 'Neuer Titel');

        $this->assertSame('Neuer Titel', $article->fresh()->title);
    }

    public function test_editing_the_content_autosaves_immediately(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $article = Article::create(['title' => 'Titel', 'type' => 'doc', 'created_by' => $admin->id]);

        Livewire::actingAs($admin)->test(ArticleEditor::class)
            ->call('startEdit', $article->id)
            ->set('formContent', "# Überschrift\n\nText.");

        $this->assertSame("# Überschrift\n\nText.", $article->fresh()->content);
    }

    public function test_clearing_the_content_stores_null(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $article = Article::create(['title' => 'Titel', 'type' => 'doc', 'content' => 'Text', 'created_by' => $admin->id]);

        Livewire::actingAs($admin)->test(ArticleEditor::class)
            ->call('startEdit', $article->id)
            ->set('formContent', '   ');

        $this->assertNull($article->fresh()->content);
    }

    public function test_switching_the_type_persists_immediately(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $article = Article::create(['title' => 'Titel', 'type' => 'doc', 'created_by' => $admin->id]);

        Livewire::actingAs($admin)->test(ArticleEditor::class)
            ->call('startEdit', $article->id)
            ->call('setType', 'blog');

        $this->assertSame('blog', $article->fresh()->type);
    }

    public function test_an_unknown_type_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $article = Article::create(['title' => 'Titel', 'type' => 'doc', 'created_by' => $admin->id]);

        Livewire::actingAs($admin)->test(ArticleEditor::class)
            ->call('startEdit', $article->id)
            ->call('setType', 'not-a-real-type');

        $this->assertSame('doc', $article->fresh()->type);
    }

    public function test_publishing_stamps_published_at_once(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $article = Article::create(['title' => 'Titel', 'type' => 'doc', 'created_by' => $admin->id]);

        Livewire::actingAs($admin)->test(ArticleEditor::class)
            ->call('startEdit', $article->id)
            ->call('togglePublish');

        $fresh = $article->fresh();
        $this->assertTrue($fresh->is_published);
        $this->assertNotNull($fresh->published_at);
    }

    public function test_unpublishing_and_republishing_never_moves_published_at(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $article = Article::create([
            'title' => 'Titel', 'type' => 'doc', 'created_by' => $admin->id,
            'is_published' => true, 'published_at' => now()->subDays(3),
        ]);
        $originalPublishedAt = $article->published_at;

        $component = Livewire::actingAs($admin)->test(ArticleEditor::class)->call('startEdit', $article->id);
        $component->call('togglePublish'); // unpublish
        $this->assertFalse($article->fresh()->is_published);

        $component->call('togglePublish'); // republish

        $this->assertTrue($article->fresh()->published_at->equalTo($originalPublishedAt));
    }

    public function test_an_admin_can_delete_an_article(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $article = Article::create(['title' => 'Titel', 'type' => 'doc', 'created_by' => $admin->id]);

        Livewire::actingAs($admin)->test(ArticleEditor::class)->call('deleteArticle', $article->id);

        $this->assertSame(0, Article::count());
    }

    public function test_deleting_the_article_currently_open_returns_to_the_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $article = Article::create(['title' => 'Titel', 'type' => 'doc', 'created_by' => $admin->id]);

        Livewire::actingAs($admin)->test(ArticleEditor::class)
            ->call('startEdit', $article->id)
            ->call('deleteArticle', $article->id)
            ->assertSet('editingId', null);
    }

    public function test_opening_a_nonexistent_article_throws(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        try {
            Livewire::actingAs($admin)->test(ArticleEditor::class)->call('startEdit', 999999);
            $this->fail('Expected a ModelNotFoundException.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }
    }

    public function test_the_list_shows_both_drafts_and_published_articles(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Article::create(['title' => 'Entwurf', 'type' => 'doc', 'created_by' => $admin->id]);
        Article::create(['title' => 'Veröffentlicht', 'type' => 'blog', 'created_by' => $admin->id, 'is_published' => true, 'published_at' => now()]);

        $titles = Livewire::actingAs($admin)->test(ArticleEditor::class)
            ->instance()
            ->articles()
            ->pluck('title');

        $this->assertContains('Entwurf', $titles);
        $this->assertContains('Veröffentlicht', $titles);
    }
}
