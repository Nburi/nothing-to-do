<?php

declare(strict_types=1);

namespace App\Support\Markdown;

use League\CommonMark\Node\Inline\AbstractInline;
use League\CommonMark\Node\Inline\DelimitedInterface;

/**
 * ++underlined++ inline text. Not part of CommonMark or GFM — there is no
 * standard underline syntax — so this is a small custom extension modeled on
 * league/commonmark's own bundled Strikethrough extension.
 */
final class Underline extends AbstractInline implements DelimitedInterface
{
    public function getOpeningDelimiter(): string
    {
        return '++';
    }

    public function getClosingDelimiter(): string
    {
        return '++';
    }
}
