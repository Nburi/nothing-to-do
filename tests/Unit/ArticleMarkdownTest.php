<?php

namespace Tests\Unit;

use App\Models\Article;
use Tests\TestCase;

class ArticleMarkdownTest extends TestCase
{
    public function test_renders_a_gfm_table(): void
    {
        $html = Article::renderMarkdown("| A | B |\n| --- | --- |\n| 1 | 2 |");

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<th>A</th>', $html);
        $this->assertStringContainsString('<td>1</td>', $html);
    }

    public function test_task_list_checkboxes_are_not_disabled(): void
    {
        $html = Article::renderMarkdown("- [ ] Schritt 1\n- [x] Schritt 2");

        $this->assertStringNotContainsString('disabled', $html);
        $this->assertStringContainsString('type="checkbox"', $html);
    }

    public function test_underline_extension_still_works(): void
    {
        $html = Article::renderMarkdown('++wichtig++');

        $this->assertStringContainsString('<u>wichtig</u>', $html);
    }

    public function test_raw_html_input_is_stripped(): void
    {
        $html = Article::renderMarkdown('<script>alert(1)</script>Text');

        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_empty_content_renders_to_empty_string(): void
    {
        $this->assertSame('', Article::renderMarkdown(null));
        $this->assertSame('', Article::renderMarkdown('   '));
    }

    public function test_preview_strips_markdown_syntax(): void
    {
        $article = new Article(['content' => "# Titel\n\n**Fett** Text.\n- [ ] Ein Punkt in einer Liste."]);

        $preview = $article->preview();

        $this->assertStringNotContainsString('#', $preview);
        $this->assertStringNotContainsString('**', $preview);
        $this->assertStringNotContainsString('[ ]', $preview);
    }

    public function test_preview_is_null_for_empty_content(): void
    {
        $article = new Article(['content' => null]);

        $this->assertNull($article->preview());
    }

    public function test_type_meta_falls_back_for_a_stale_type(): void
    {
        $article = new Article(['type' => 'no-longer-a-real-type']);

        $this->assertSame(Article::TYPES[Article::DEFAULT_TYPE]['label'], $article->typeLabel());
    }
}
