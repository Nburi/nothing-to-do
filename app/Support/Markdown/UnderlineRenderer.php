<?php

declare(strict_types=1);

namespace App\Support\Markdown;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

final class UnderlineRenderer implements NodeRendererInterface
{
    /**
     * @param Underline $node
     *
     * {@inheritDoc}
     *
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        Underline::assertInstanceOf($node);

        return new HtmlElement('u', $node->data->get('attributes'), $childRenderer->renderNodes($node->children()));
    }
}
