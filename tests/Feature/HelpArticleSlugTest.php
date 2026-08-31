<?php

namespace Tests\Feature;

use App\Models\HelpArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpArticleSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_a_url_safe_slug_from_the_title(): void
    {
        $this->assertSame('wie-lege-ich-eine-aufgabe-an', HelpArticle::generateSlug('Wie lege ich eine Aufgabe an?'));
    }

    public function test_a_title_with_nothing_slug_worthy_falls_back_to_a_generic_slug(): void
    {
        $this->assertSame('artikel', HelpArticle::generateSlug('???'));
    }

    public function test_a_colliding_title_gets_a_numeric_suffix(): void
    {
        HelpArticle::create(['title' => 'Titel', 'slug' => 'titel']);

        $this->assertSame('titel-2', HelpArticle::generateSlug('Titel'));
    }

    public function test_a_second_collision_keeps_incrementing(): void
    {
        HelpArticle::create(['title' => 'Titel', 'slug' => 'titel']);
        HelpArticle::create(['title' => 'Titel', 'slug' => 'titel-2']);

        $this->assertSame('titel-3', HelpArticle::generateSlug('Titel'));
    }

    public function test_ignore_id_excludes_the_article_being_edited_from_its_own_collision_check(): void
    {
        $article = HelpArticle::create(['title' => 'Titel', 'slug' => 'titel']);

        $this->assertSame('titel', HelpArticle::generateSlug('Titel', $article->id));
    }
}
