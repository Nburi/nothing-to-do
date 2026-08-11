<?php

declare(strict_types=1);

namespace App\Support\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;

/** Registers ++underlined++ inline formatting, used for Task notes. */
final class UnderlineExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addDelimiterProcessor(new UnderlineDelimiterProcessor());
        $environment->addRenderer(Underline::class, new UnderlineRenderer());
    }
}
