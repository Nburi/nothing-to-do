<?php

namespace App\Mcp\Tools\Admin;

use App\Models\FeatureAnnouncement;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Shared by create/update_announcement: turns the tool arguments into model
 * attributes using the exact rules of Admin\AnnouncementEditor::save() — the
 * internal-page link and the external link are mutually exclusive, the
 * highlight selector only applies to a module link, and "only for module
 * users" only to a scopable module. Never touches is_published.
 */
trait BuildsAnnouncementAttributes
{
    /** @return array<string, mixed> JSON-schema properties shared by both tools. */
    protected function announcementSchemaProperties(): array
    {
        return [
            'type' => ['type' => 'string', 'enum' => array_keys(FeatureAnnouncement::TYPES), 'description' => 'info (default), maintenance, warning, release.'],
            'link_type' => ['type' => 'string', 'enum' => ['none', 'module', 'external'], 'description' => 'What the toast links to. Fields of the other kinds are cleared.'],
            'related_module' => ['type' => 'string', 'enum' => array_keys(FeatureAnnouncement::linkableModules()), 'description' => 'For link_type=module: the app page to open.'],
            'highlight_selector' => ['type' => 'string', 'description' => 'For link_type=module: optional CSS selector flashed on arrival (e.g. "#list-concept").'],
            'only_for_module_users' => ['type' => 'boolean', 'description' => 'For link_type=module: only show to people who have already opened that page.'],
            'external_url' => ['type' => 'string', 'description' => 'For link_type=external: full https URL.'],
            'external_link_label' => ['type' => 'string', 'description' => 'For link_type=external: button text (default "Mehr erfahren").'],
        ];
    }

    /**
     * @param  array<string, mixed>  $values  effective values (existing row overlaid with the arguments)
     * @return array<string, mixed>
     */
    protected function announcementAttributes(array $values): array
    {
        $values['title'] = trim((string) ($values['title'] ?? ''));
        $values['description'] = trim((string) ($values['description'] ?? ''));

        $linkType = $values['link_type'] ?? 'none';

        $data = Validator::make($values, [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:500'],
            'type' => ['required', Rule::in(array_keys(FeatureAnnouncement::TYPES))],
            'link_type' => ['required', Rule::in(['none', 'module', 'external'])],
            'related_module' => [Rule::requiredIf($linkType === 'module'), 'nullable', Rule::in(array_keys(FeatureAnnouncement::linkableModules()))],
            'external_url' => [Rule::requiredIf($linkType === 'external'), 'nullable', 'url', 'max:2048'],
            'external_link_label' => ['nullable', 'string', 'max:100'],
            'highlight_selector' => ['nullable', 'string', 'max:255'],
            'only_for_module_users' => ['nullable', 'boolean'],
        ])->validate();

        $label = trim((string) ($data['external_link_label'] ?? ''));
        $selector = trim((string) ($data['highlight_selector'] ?? ''));
        $isModule = $linkType === 'module';

        return [
            'title' => $data['title'],
            'description' => $data['description'],
            'type' => $data['type'],
            'related_module' => $isModule ? $data['related_module'] : null,
            'only_for_module_users' => $isModule
                && FeatureAnnouncement::isScopableModule($data['related_module'] ?? '')
                && (bool) ($data['only_for_module_users'] ?? false),
            'external_url' => $linkType === 'external' ? $data['external_url'] : null,
            'external_link_label' => $linkType === 'external' && $label !== '' ? $label : null,
            'highlight_selector' => $isModule && $selector !== '' ? $selector : null,
        ];
    }

    /** @return array<string, mixed> */
    protected function announcementResult(FeatureAnnouncement $a): array
    {
        return [
            'id' => $a->id,
            'title' => $a->title,
            'type' => $a->type,
            'related_module' => $a->related_module,
            'external_url' => $a->external_url,
            'is_published' => $a->is_published,
        ];
    }
}
