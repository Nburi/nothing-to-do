<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpTool;
use App\Models\FeatureAnnouncement;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\SupportRequest;

/**
 * Base for the admin MCP tools (support queue, Hilfe-Center, announcements).
 * Offered only to users.is_admin accounts — see McpTool::requiresAdmin().
 *
 * The hard rule shared by every tool below: an AI agent may draft and edit, but
 * never publish. Nothing here writes is_published/published_at, create tools
 * always produce drafts, and an already-published article/announcement is
 * refused for edit and delete (publishedGuard) — otherwise an "edit" would be a
 * silent live publish to every user. Publishing, unpublishing and changing live
 * content stay a deliberate human action in the admin UI.
 */
abstract class AdminTool extends McpTool
{
    public function requiresAdmin(): bool
    {
        return true;
    }

    protected function supportRequest(mixed $id): SupportRequest
    {
        $request = SupportRequest::with('user:id,name')->find((int) $id);

        if ($request === null) {
            throw new McpToolExecutionException("No support request with id {$id} found.");
        }

        return $request;
    }

    protected function helpArticle(mixed $id): HelpArticle
    {
        $article = HelpArticle::find((int) $id);

        if ($article === null) {
            throw new McpToolExecutionException("No help article with id {$id} found.");
        }

        return $article;
    }

    protected function announcement(mixed $id): FeatureAnnouncement
    {
        $announcement = FeatureAnnouncement::find((int) $id);

        if ($announcement === null) {
            throw new McpToolExecutionException("No announcement with id {$id} found.");
        }

        return $announcement;
    }

    protected function helpCategory(mixed $id): HelpCategory
    {
        $category = HelpCategory::find((int) $id);

        if ($category === null) {
            throw new McpToolExecutionException("No help category with id {$id} found.");
        }

        return $category;
    }

    /** Resolves a parent id; only top-level categories qualify (the sidebar renders two levels). */
    protected function parentCategory(mixed $id): ?HelpCategory
    {
        if ($id === null) {
            return null;
        }

        $parent = $this->helpCategory($id);

        if ($parent->parent_id !== null) {
            throw new McpToolExecutionException('Only two levels exist: the parent must be a top-level category.');
        }

        return $parent;
    }

    /**
     * A category whose subtree holds a published article is live structure:
     * renaming, moving or deleting it would change the reader's sidebar (and,
     * for delete, pull published articles out of their folder).
     */
    protected function categoryIsLive(HelpCategory $category): bool
    {
        $ids = HelpCategory::where('parent_id', $category->id)->pluck('id')->push($category->id);

        return HelpArticle::published()->whereIn('help_category_id', $ids)->exists();
    }

    /** Refuses to touch content that is already live. */
    protected function publishedGuard(bool $isPublished, string $what, string $verb): void
    {
        if ($isPublished) {
            throw new McpToolExecutionException(
                "This {$what} is already published, so it cannot be {$verb} through MCP — that would change what every "
                .'user sees. Ask the admin to unpublish it in the admin area first (or change it there directly).'
            );
        }
    }

    protected function confirmTitle(string $actual, mixed $given): void
    {
        if (! is_string($given) || $given !== $actual) {
            throw new McpToolExecutionException(
                "confirm_title did not match. The current title is: \"{$actual}\". "
                .'Pass that exact string as confirm_title to proceed, after checking with the user.'
            );
        }
    }
}
