<?php

namespace App\Mcp;

use App\Mcp\Exceptions\McpUnknownToolException;
use App\Mcp\Tools\CompleteTaskTool;
use App\Mcp\Tools\CreateAgendaEntryTool;
use App\Mcp\Tools\CreateTaskTool;
use App\Mcp\Tools\CreateTasksTool;
use App\Mcp\Tools\UpdateTasksTool;
use App\Mcp\Tools\DeleteTaskTool;
use App\Mcp\Tools\GetBoardTool;
use App\Mcp\Tools\GetProgressTool;
use App\Mcp\Tools\GetSettingsTool;
use App\Mcp\Tools\GetTaskTool;
use App\Mcp\Tools\ListAgendaEntriesTool;
use App\Mcp\Tools\ListCategoriesTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListTaskGroupsTool;
use App\Mcp\Tools\ListTasksTool;
use App\Mcp\Tools\ReopenTaskTool;
use App\Mcp\Tools\SetDefaultLandingPageTool;
use App\Mcp\Tools\SetListConceptTool;
use App\Mcp\Tools\SetModuleVisibilityTool;
use App\Mcp\Tools\SetTaskOrderTool;
use App\Mcp\Tools\UpdateTaskTool;
use App\Mcp\Tools\Admin\AnswerSupportRequestTool;
use App\Mcp\Tools\Admin\CreateAnnouncementTool;
use App\Mcp\Tools\Admin\CreateHelpArticleTool;
use App\Mcp\Tools\Admin\DeleteAnnouncementTool;
use App\Mcp\Tools\Admin\DeleteHelpArticleTool;
use App\Mcp\Tools\Admin\GetHelpArticleTool;
use App\Mcp\Tools\Admin\GetSupportRequestTool;
use App\Mcp\Tools\Admin\ListAnnouncementsTool;
use App\Mcp\Tools\Admin\ListHelpArticlesTool;
use App\Mcp\Tools\Admin\ListHelpCategoriesTool;
use App\Mcp\Tools\Admin\ListSupportRequestsTool;
use App\Mcp\Tools\Admin\UpdateAnnouncementTool;
use App\Mcp\Tools\Admin\UpdateHelpArticleTool;
use App\Mcp\Tools\Admin\CreateHelpCategoryTool;
use App\Mcp\Tools\Admin\UpdateHelpCategoryTool;
use App\Mcp\Tools\Admin\DeleteHelpCategoryTool;
use App\Mcp\Tools\GetProjectTool;
use App\Mcp\Tools\GetTaskGroupTool;
use App\Mcp\Tools\GetAgendaEntryTool;
use App\Mcp\Tools\ListAgendaSpacesTool;
use App\Mcp\Tools\CreateProjectTool;
use App\Mcp\Tools\UpdateProjectTool;
use App\Mcp\Tools\CreateTaskGroupTool;
use App\Mcp\Tools\UpdateTaskGroupTool;
use App\Mcp\Tools\AddGroupNoteTool;
use App\Mcp\Tools\UpdateGroupNoteTool;
use App\Mcp\Tools\UpdateAgendaEntryTool;
use App\Mcp\Tools\DeleteProjectTool;
use App\Mcp\Tools\DissolveTaskGroupTool;
use App\Mcp\Tools\DeleteGroupNoteTool;
use App\Mcp\Tools\DeleteAgendaEntryTool;
use App\Mcp\Tools\ListScheduleEventsTool;
use App\Mcp\Tools\GetScheduleEventTool;
use App\Mcp\Tools\GetFocusSessionTool;
use App\Mcp\Tools\ListEventTemplatesTool;
use App\Mcp\Tools\ListSchedulePausesTool;
use App\Mcp\Tools\GetDayFrameTool;
use App\Mcp\Tools\CreateScheduleEventTool;
use App\Mcp\Tools\UpdateScheduleEventTool;
use App\Mcp\Tools\ControlFocusSessionTool;
use App\Mcp\Tools\ApplyEventTemplateTool;
use App\Mcp\Tools\CreateWeekPlanBlockTool;
use App\Mcp\Tools\UpdateWeekPlanBlockTool;
use App\Mcp\Tools\CreateCategoryTool;
use App\Mcp\Tools\UpdateCategoryTool;
use App\Mcp\Tools\SetCategoryTaskLinkTool;
use App\Mcp\Tools\ClearCategoryTaskLinkTool;
use App\Mcp\Tools\CreateCategoryAttributeTool;
use App\Mcp\Tools\UpdateCategoryAttributeTool;
use App\Mcp\Tools\PauseScheduleTool;
use App\Mcp\Tools\UnpauseScheduleTool;
use App\Mcp\Tools\SetDayFrameTool;
use App\Mcp\Tools\DeleteScheduleEventTool;
use App\Mcp\Tools\DeleteEventTemplateTool;
use App\Mcp\Tools\DeleteCategoryTool;
use App\Mcp\Tools\DeleteCategoryAttributeTool;
use App\Mcp\Tools\GetDayOverviewTool;
use App\Mcp\Tools\GetWeekReviewTool;
use App\Mcp\Tools\GetPlannerTool;
use App\Mcp\Tools\GetEmergencyModeTool;
use App\Mcp\Tools\ListCraftIdeasTool;
use App\Mcp\Tools\RescheduleOverdueTasksTool;
use App\Mcp\Tools\PlanTaskTool;
use App\Mcp\Tools\SetDayPlanTool;
use App\Mcp\Tools\UnplanTaskTool;
use App\Mcp\Tools\AutoPlanBacklogTool;
use App\Mcp\Tools\AddStandardTaskTool;
use App\Mcp\Tools\StartEmergencyModeTool;
use App\Mcp\Tools\EndEmergencyModeTool;
use App\Mcp\Tools\ArrangeEmergencyTasksTool;
use App\Mcp\Tools\CreateCraftIdeaTool;
use App\Mcp\Tools\UpdateCraftIdeaTool;
use App\Mcp\Tools\DeleteCraftIdeaTool;
use App\Mcp\Tools\SearchHelpTool;
use App\Mcp\Tools\ReadHelpArticleTool;
use App\Mcp\Tools\ListMySupportRequestsTool;
use App\Mcp\Tools\UpdateSettingsTool;
use App\Mcp\Tools\SubmitSupportRequestTool;
use App\Models\User;
use App\Services\AppModules;

/**
 * The MCP tool registry: what tools exist, and — the load-bearing part —
 * which of them are actually visible to a given user/token right now. Both
 * tools/list and tools/call go through availableTools(), so the two can
 * never disagree about what's callable (the signature moment: what
 * tools/list doesn't show, tools/call also refuses, with the identical
 * "unknown tool" error either way — see McpUnknownToolException).
 */
class McpServer
{
    /** @var list<McpTool> */
    protected array $tools;

    public function __construct()
    {
        $this->tools = [
            // Read
            new ListTasksTool,
            new GetBoardTool,
            new GetTaskTool,
            new ListProjectsTool,
            new ListTaskGroupsTool,
            new ListCategoriesTool,
            new ListAgendaEntriesTool,
            new GetProgressTool,
            new GetSettingsTool,
            new GetProjectTool,
            new GetTaskGroupTool,
            new GetAgendaEntryTool,
            new ListAgendaSpacesTool,
            new ListScheduleEventsTool,
            new GetScheduleEventTool,
            new GetFocusSessionTool,
            new ListEventTemplatesTool,
            new ListSchedulePausesTool,
            new GetDayFrameTool,
            new GetDayOverviewTool,
            new GetWeekReviewTool,
            new GetPlannerTool,
            new GetEmergencyModeTool,
            new ListCraftIdeasTool,
            new SearchHelpTool,
            new ReadHelpArticleTool,
            new ListMySupportRequestsTool,
            // Write
            new CreateTaskTool,
            new UpdateTaskTool,
            new CreateTasksTool,
            new UpdateTasksTool,
            new CompleteTaskTool,
            new ReopenTaskTool,
            new SetTaskOrderTool,
            new SetListConceptTool,
            new SetModuleVisibilityTool,
            new SetDefaultLandingPageTool,
            new CreateAgendaEntryTool,
            new CreateProjectTool,
            new UpdateProjectTool,
            new CreateTaskGroupTool,
            new UpdateTaskGroupTool,
            new AddGroupNoteTool,
            new UpdateGroupNoteTool,
            new UpdateAgendaEntryTool,
            new CreateScheduleEventTool,
            new UpdateScheduleEventTool,
            new ControlFocusSessionTool,
            new ApplyEventTemplateTool,
            new CreateWeekPlanBlockTool,
            new UpdateWeekPlanBlockTool,
            new CreateCategoryTool,
            new UpdateCategoryTool,
            new SetCategoryTaskLinkTool,
            new ClearCategoryTaskLinkTool,
            new CreateCategoryAttributeTool,
            new UpdateCategoryAttributeTool,
            new PauseScheduleTool,
            new UnpauseScheduleTool,
            new SetDayFrameTool,
            new RescheduleOverdueTasksTool,
            new PlanTaskTool,
            new SetDayPlanTool,
            new UnplanTaskTool,
            new AutoPlanBacklogTool,
            new AddStandardTaskTool,
            new StartEmergencyModeTool,
            new EndEmergencyModeTool,
            new ArrangeEmergencyTasksTool,
            new CreateCraftIdeaTool,
            new UpdateCraftIdeaTool,
            new UpdateSettingsTool,
            new SubmitSupportRequestTool,
            // Delete
            new DeleteTaskTool,
            new DeleteCraftIdeaTool,
            new DeleteScheduleEventTool,
            new DeleteEventTemplateTool,
            new DeleteCategoryTool,
            new DeleteCategoryAttributeTool,
            new DeleteProjectTool,
            new DissolveTaskGroupTool,
            new DeleteGroupNoteTool,
            new DeleteAgendaEntryTool,
            // Admin only (users.is_admin): support queue, Hilfe-Center, announcements
            new ListSupportRequestsTool,
            new GetSupportRequestTool,
            new AnswerSupportRequestTool,
            new ListHelpCategoriesTool,
            new ListHelpArticlesTool,
            new CreateHelpCategoryTool,
            new UpdateHelpCategoryTool,
            new DeleteHelpCategoryTool,
            new GetHelpArticleTool,
            new CreateHelpArticleTool,
            new UpdateHelpArticleTool,
            new DeleteHelpArticleTool,
            new ListAnnouncementsTool,
            new CreateAnnouncementTool,
            new UpdateAnnouncementTool,
            new DeleteAnnouncementTool,
        ];
    }

    /**
     * @param  callable(string): bool  $tokenCan  typically $user->tokenCan(...) — see
     *                                            McpController::tokenCan(). Taking a callable (rather than a raw abilities
     *                                            array) means this class never has to know how to read a Sanctum token's
     *                                            abilities itself — Laravel\Sanctum\HasApiTokens::tokenCan() already
     *                                            handles both a real PersonalAccessToken (abilities array, '*' wildcard)
     *                                            and a session-authenticated TransientToken (always true) correctly.
     * @return list<McpTool>
     */
    public function availableTools(User $user, callable $tokenCan): array
    {
        return array_values(array_filter(
            $this->tools,
            fn (McpTool $tool) => $this->isAvailable($tool, $user, $tokenCan),
        ));
    }

    /**
     * Every registered tool instance, in registration order — the shared
     * source both transports build their tool list from (the Sanctum
     * endpoint via availableTools() below, the OAuth server by wrapping each
     * one in App\Mcp\Servers\BridgedTool).
     *
     * @return list<McpTool>
     */
    public function tools(): array
    {
        return $this->tools;
    }

    /**
     * @param  callable(string): bool  $tokenCan
     */
    public function isAvailableTo(McpTool $tool, User $user, callable $tokenCan): bool
    {
        return $this->isAvailable($tool, $user, $tokenCan);
    }

    protected function isAvailable(McpTool $tool, User $user, callable $tokenCan): bool
    {
        if ($tool->requiresAdmin() && ! $user->is_admin) {
            return false;
        }

        if ($tool->requiredAbility() !== null && ! $tokenCan($tool->requiredAbility())) {
            return false;
        }

        if ($tool->requiredModule() !== null && ! AppModules::isVisible($user, $tool->requiredModule())) {
            return false;
        }

        if (! $tool->isEnabledFor($user)) {
            return false;
        }

        return true;
    }

    /**
     * @param  callable(string): bool  $tokenCan
     * @return list<array{name: string, description: string, inputSchema: array, annotations: array}>
     */
    public function listToolDefinitions(User $user, callable $tokenCan): array
    {
        return array_map(fn (McpTool $tool) => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'inputSchema' => $tool->inputSchema(),
            'annotations' => $tool->annotations(),
        ], $this->availableTools($user, $tokenCan));
    }

    /**
     * @param  callable(string): bool  $tokenCan
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     *
     * @throws McpUnknownToolException when $name doesn't exist OR isn't
     *                                 available to this user/token — deliberately the same error either way.
     */
    public function call(User $user, callable $tokenCan, string $name, array $arguments): array
    {
        foreach ($this->availableTools($user, $tokenCan) as $tool) {
            if ($tool->name() === $name) {
                return $tool->handle($user, $arguments);
            }
        }

        throw new McpUnknownToolException($name);
    }

    /**
     * Every registered tool, regardless of availability for any particular
     * user/token — reference documentation only (/docs/mcp). Never used to
     * decide what tools/list or tools/call actually expose.
     *
     * @return list<array{name: string, description: string, inputSchema: array, annotations: array, requiredAbility: ?string, requiredModule: ?string, requiresAdmin: bool}>
     */
    public function allToolDefinitions(): array
    {
        return array_map(fn (McpTool $tool) => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'inputSchema' => $tool->inputSchema(),
            'annotations' => $tool->annotations(),
            'requiredAbility' => $tool->requiredAbility(),
            'requiredModule' => $tool->requiredModule(),
            'requiredFeature' => $tool->requiredFeatureLabel(),
            'requiresAdmin' => $tool->requiresAdmin(),
        ], $this->tools);
    }
}
