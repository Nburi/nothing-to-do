<?php

namespace Tests\Feature;

use App\Livewire\PublicHelp;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The public, guest-readable mirror of the Hilfe-Center at /hilfe — see
 * CLAUDE.md, "Hilfe-Center & Support", and App\Livewire\PublicHelp's own
 * docblock for why this is a separate route/component from the
 * authenticated /app/help (covered by HelpCenterTest).
 */
class PublicHelpCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_open_the_public_help_center(): void
    {
        $this->get(route('help.public'))->assertOk();
    }

    public function test_only_published_articles_appear_in_the_tree(): void
    {
        $category = HelpCategory::create(['name' => 'Board']);
        HelpArticle::create([
            'title' => 'Sichtbar', 'slug' => HelpArticle::generateSlug('Sichtbar'),
            'help_category_id' => $category->id, 'is_published' => true,
        ]);
        HelpArticle::create([
            'title' => 'Entwurf', 'slug' => HelpArticle::generateSlug('Entwurf'),
            'help_category_id' => $category->id, 'is_published' => false,
        ]);

        $tree = Livewire::test(PublicHelp::class)->instance()->tree();

        $titles = $tree->first()->articles->pluck('title');
        $this->assertContains('Sichtbar', $titles);
        $this->assertNotContains('Entwurf', $titles);
    }

    public function test_opening_a_published_article_via_its_slug_selects_it(): void
    {
        $article = HelpArticle::create([
            'title' => 'Titel', 'slug' => HelpArticle::generateSlug('Titel'),
            'content' => 'Text', 'is_published' => true,
        ]);

        Livewire::test(PublicHelp::class, ['slug' => $article->slug])
            ->assertSet('slug', $article->slug);

        $selected = Livewire::test(PublicHelp::class, ['slug' => $article->slug])->instance()->selectedArticle();
        $this->assertSame($article->id, $selected->id);
    }

    public function test_an_unpublished_articles_slug_is_silently_ignored(): void
    {
        $draft = HelpArticle::create([
            'title' => 'Entwurf', 'slug' => HelpArticle::generateSlug('Entwurf'), 'is_published' => false,
        ]);

        $selected = Livewire::test(PublicHelp::class, ['slug' => $draft->slug])->instance()->selectedArticle();
        $this->assertNull($selected);
    }

    public function test_a_foreign_or_missing_slug_is_silently_ignored(): void
    {
        $selected = Livewire::test(PublicHelp::class, ['slug' => 'does-not-exist'])->instance()->selectedArticle();
        $this->assertNull($selected);
    }

    public function test_content_html_renders_markdown_for_the_selected_article(): void
    {
        $article = HelpArticle::create([
            'title' => 'Mit Tabelle', 'slug' => HelpArticle::generateSlug('Mit Tabelle'), 'is_published' => true,
            'content' => "| A | B |\n| --- | --- |\n| 1 | 2 |",
        ]);

        $html = Livewire::test(PublicHelp::class, ['slug' => $article->slug])->instance()->contentHtml();

        $this->assertStringContainsString('<table>', $html);
    }

    public function test_the_page_title_and_canonical_url_reflect_the_selected_article(): void
    {
        $article = HelpArticle::create([
            'title' => 'Wie funktioniert die Inbox?', 'slug' => HelpArticle::generateSlug('Wie funktioniert die Inbox?'),
            'is_published' => true,
        ]);

        $instance = Livewire::test(PublicHelp::class, ['slug' => $article->slug])->instance();

        $this->assertSame('Wie funktioniert die Inbox? – Hilfe – nothing-to-do', $instance->pageTitle());
        $this->assertSame(url('/hilfe/'.$article->slug), $instance->canonicalUrl());
    }

    public function test_the_index_page_has_a_generic_title_and_no_json_ld(): void
    {
        $instance = Livewire::test(PublicHelp::class)->instance();

        $this->assertSame('Hilfe-Center – nothing-to-do', $instance->pageTitle());
        $this->assertSame(url('/hilfe'), $instance->canonicalUrl());
        $this->assertNull($instance->jsonLd());
    }

    public function test_a_selected_article_gets_an_article_json_ld_schema(): void
    {
        $article = HelpArticle::create([
            'title' => 'Titel', 'slug' => HelpArticle::generateSlug('Titel'),
            'is_published' => true, 'published_at' => now(),
        ]);

        $jsonLd = Livewire::test(PublicHelp::class, ['slug' => $article->slug])->instance()->jsonLd();

        $decoded = json_decode($jsonLd, true);
        $this->assertSame('Article', $decoded['@type']);
        $this->assertSame('Titel', $decoded['headline']);
        $this->assertSame(url('/hilfe/'.$article->slug), $decoded['mainEntityOfPage']['@id']);
    }

    public function test_the_sitemap_lists_only_published_articles(): void
    {
        HelpArticle::create([
            'title' => 'Veröffentlicht', 'slug' => HelpArticle::generateSlug('Veröffentlicht'), 'is_published' => true,
        ]);
        HelpArticle::create([
            'title' => 'Entwurf', 'slug' => HelpArticle::generateSlug('Entwurf'), 'is_published' => false,
        ]);

        $xml = $this->get(route('sitemap'))->assertOk()->getContent();

        $this->assertStringContainsString('/hilfe/veroffentlicht', $xml);
        $this->assertStringNotContainsString('/hilfe/entwurf', $xml);
    }
}
