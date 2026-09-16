<?php

namespace Tests\Feature;

use App\Livewire\Admin\HelpEditor;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminHelpEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_admin_cannot_open_the_editor(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route('admin.help'))->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.help'))->assertRedirect(route('login'));
    }

    public function test_an_admin_can_open_the_editor(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.help'))->assertOk();
    }

    public function test_an_admin_can_create_a_root_category(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)->test(HelpEditor::class)
            ->call('startCreatingRootCategory')
            ->set('newRootCategoryName', 'Board & Aufgaben')
            ->call('saveRootCategory');

        $category = HelpCategory::sole();
        $this->assertSame('Board & Aufgaben', $category->name);
        $this->assertNull($category->parent_id);
    }

    public function test_an_admin_can_create_a_subcategory(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $root = HelpCategory::create(['name' => 'Board']);

        Livewire::actingAs($admin)->test(HelpEditor::class)
            ->call('startCreatingSubcategory', $root->id)
            ->set('newSubcategoryName', 'Projekte')
            ->call('saveSubcategory');

        $sub = HelpCategory::where('name', 'Projekte')->sole();
        $this->assertSame($root->id, $sub->parent_id);
    }

    public function test_an_admin_can_rename_a_category(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = HelpCategory::create(['name' => 'Alt']);

        Livewire::actingAs($admin)->test(HelpEditor::class)
            ->call('renameCategory', $category->id, 'Neu');

        $this->assertSame('Neu', $category->fresh()->name);
    }

    public function test_deleting_a_category_drops_its_articles_to_uncategorized_not_gone(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = HelpCategory::create(['name' => 'Wird gelöscht']);
        $article = HelpArticle::create(['title' => 'Bleibt', 'help_category_id' => $category->id]);

        Livewire::actingAs($admin)->test(HelpEditor::class)->call('deleteCategory', $category->id);

        $this->assertSame(0, HelpCategory::count());
        $this->assertNotNull($article->fresh());
        $this->assertNull($article->fresh()->help_category_id);
    }

    public function test_deleting_a_category_drops_its_subcategories_to_top_level(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $root = HelpCategory::create(['name' => 'Root']);
        $sub = HelpCategory::create(['name' => 'Sub', 'parent_id' => $root->id]);

        Livewire::actingAs($admin)->test(HelpEditor::class)->call('deleteCategory', $root->id);

        $this->assertNull($sub->fresh()->parent_id);
    }

    public function test_creating_an_article_opens_it_immediately_as_a_draft(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = HelpCategory::create(['name' => 'Board']);

        Livewire::actingAs($admin)->test(HelpEditor::class)
            ->call('createArticle', $category->id)
            ->assertSet('formCategoryId', $category->id);

        $article = HelpArticle::sole();
        $this->assertFalse($article->is_published);
        $this->assertSame($admin->id, $article->created_by);
        $this->assertSame($category->id, $article->help_category_id);
    }

    public function test_editing_the_title_autosaves_immediately(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $article = HelpArticle::create(['title' => 'Alt']);

        Livewire::actingAs($admin)->test(HelpEditor::class)
            ->call('startEdit', $article->id)
            ->set('formTitle', 'Neuer Titel');

        $this->assertSame('Neuer Titel', $article->fresh()->title);
    }

    public function test_editing_the_content_autosaves_immediately(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $article = HelpArticle::create(['title' => 'Titel']);

        Livewire::actingAs($admin)->test(HelpEditor::class)
            ->call('startEdit', $article->id)
            ->set('formContent', "# Übersicht\n\nText.");

        $this->assertSame("# Übersicht\n\nText.", $article->fresh()->content);
    }

    public function test_moving_an_article_to_another_category_persists(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $categoryA = HelpCategory::create(['name' => 'A']);
        $categoryB = HelpCategory::create(['name' => 'B']);
        $article = HelpArticle::create(['title' => 'Titel', 'help_category_id' => $categoryA->id]);

        Livewire::actingAs($admin)->test(HelpEditor::class)
            ->call('startEdit', $article->id)
            ->set('formCategoryId', (string) $categoryB->id);

        $this->assertSame($categoryB->id, $article->fresh()->help_category_id);
    }

    public function test_setting_category_to_the_empty_option_uncategorizes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = HelpCategory::create(['name' => 'A']);
        $article = HelpArticle::create(['title' => 'Titel', 'help_category_id' => $category->id]);

        Livewire::actingAs($admin)->test(HelpEditor::class)
            ->call('startEdit', $article->id)
            ->set('formCategoryId', '');

        $this->assertNull($article->fresh()->help_category_id);
    }

    public function test_publishing_stamps_published_at_once(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $article = HelpArticle::create(['title' => 'Titel']);

        Livewire::actingAs($admin)->test(HelpEditor::class)
            ->call('startEdit', $article->id)
            ->call('togglePublish');

        $fresh = $article->fresh();
        $this->assertTrue($fresh->is_published);
        $this->assertNotNull($fresh->published_at);
    }

    public function test_unpublishing_and_republishing_never_moves_published_at(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $article = HelpArticle::create([
            'title' => 'Titel', 'is_published' => true, 'published_at' => now()->subDays(3),
        ]);
        $originalPublishedAt = $article->published_at;

        $component = Livewire::actingAs($admin)->test(HelpEditor::class)->call('startEdit', $article->id);
        $component->call('togglePublish');
        $this->assertFalse($article->fresh()->is_published);

        $component->call('togglePublish');
        $this->assertTrue($article->fresh()->published_at->equalTo($originalPublishedAt));
    }

    public function test_an_admin_can_delete_an_article(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $article = HelpArticle::create(['title' => 'Titel']);

        Livewire::actingAs($admin)->test(HelpEditor::class)->call('deleteArticle', $article->id);

        $this->assertSame(0, HelpArticle::count());
    }

    public function test_opening_a_nonexistent_article_throws(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        try {
            Livewire::actingAs($admin)->test(HelpEditor::class)->call('startEdit', 999999);
            $this->fail('Expected a ModelNotFoundException.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }
    }

    public function test_creating_an_article_stamps_a_slug_from_its_initial_title(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)->test(HelpEditor::class)->call('createArticle', null);

        $this->assertSame('neuer-artikel', HelpArticle::sole()->slug);
    }

    public function test_the_slug_tracks_the_title_while_still_a_draft(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $article = HelpArticle::create(['title' => 'Alt', 'slug' => 'alt', 'is_published' => false]);

        Livewire::actingAs($admin)->test(HelpEditor::class)
            ->call('startEdit', $article->id)
            ->set('formTitle', 'Neuer Titel');

        $this->assertSame('neuer-titel', $article->fresh()->slug);
    }

    public function test_the_slug_freezes_once_the_article_is_published(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $article = HelpArticle::create([
            'title' => 'Ursprünglicher Titel', 'slug' => 'ursprunglicher-titel', 'is_published' => true,
        ]);

        Livewire::actingAs($admin)->test(HelpEditor::class)
            ->call('startEdit', $article->id)
            ->set('formTitle', 'Ganz anderer Titel');

        $fresh = $article->fresh();
        $this->assertSame('Ganz anderer Titel', $fresh->title);
        $this->assertSame('ursprunglicher-titel', $fresh->slug);
    }

    public function test_the_tree_nests_subcategories_under_their_parent(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $root = HelpCategory::create(['name' => 'Root']);
        HelpCategory::create(['name' => 'Sub', 'parent_id' => $root->id]);

        $tree = Livewire::actingAs($admin)->test(HelpEditor::class)->instance()->tree();

        $this->assertCount(1, $tree);
        $this->assertCount(1, $tree->first()->children);
        $this->assertSame('Sub', $tree->first()->children->first()->name);
    }
}
