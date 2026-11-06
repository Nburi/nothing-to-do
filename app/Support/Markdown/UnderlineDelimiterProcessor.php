<?php

declare(strict_types=1);

namespace App\Support\Markdown;

use League\CommonMark\Delimiter\DelimiterInterface;
use League\CommonMark\Delimiter\Processor\CacheableDelimiterProcessorInterface;
use League\CommonMark\Node\Inline\AbstractStringContainer;

/** Matches runs of exactly two '+' characters (++text++) and wraps them in an Underline node. */
final class UnderlineDelimiterProcessor implements CacheableDelimiterProcessorInterface
{
    public function getOpeningCharacter(): string
    {
        return '+';
    }

    public function getClosingCharacter(): string
    {
        return '+';
    }

    public function getMinLength(): int
    {
        return 2;
    }

    public function getDelimiterUse(DelimiterInterface $opener, DelimiterInterface $closer): int
    {
        if ($opener->getLength() !== 2 || $closer->getLength() !== 2) {
            return 0;
        }

        return 2;
    }

    public function process(AbstractStringContainer $opener, AbstractStringContainer $closer, int $delimiterUse): void
    {
        $underline = new Underline();

        $tmp = $opener->next();
        while ($tmp !== null && $tmp !== $closer) {
            $next = $tmp->next();
            $underline->appendChild($tmp);
            $tmp = $next;
        }

        $opener->insertAfter($underline);
    }

    public function getCacheKey(DelimiterInterface $closer): string
    {
        return '+'.$closer->getLength();
    }
}
