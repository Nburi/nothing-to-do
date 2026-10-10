<?php

namespace App\Mcp\Tools\Concerns;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Models\CategoryAttribute;
use App\Models\EventCategory;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Schema and normalisation shared by create_/update_category_attribute (mirrors Settings::saveAttribute). */
trait CategoryAttributeFields
{
    use ScheduleToolDefaults;

    /** @return array<string, mixed> */
    protected static function attributeProperties(): array
    {
        return [
            'name' => ['type' => 'string', 'description' => 'Max 255 characters, e.g. "Trainingstyp".'],
            'type' => ['type' => 'string', 'enum' => array_keys(CategoryAttribute::TYPES), 'description' => 'text | number | select | checkbox.'],
            'unit' => ['type' => ['string', 'null'], 'description' => 'Only for type "number", max 32 characters, e.g. "Min".'],
            'options' => [
                'type' => 'array',
                'description' => 'Required for type "select": the allowed choices.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'label' => ['type' => 'string'],
                        'color' => ['type' => 'string', 'enum' => CategoryAttribute::OPTION_COLORS],
                    ],
                    'required' => ['label'],
                ],
            ],
        ];
    }

    protected function ownedCategory(User $user, mixed $id): EventCategory
    {
        $category = $user->eventCategories()->find((int) $id);

        if ($category === null) {
            throw new McpToolExecutionException("No category with id {$id} found for this account.");
        }

        return $category;
    }

    /**
     * Validate a (partial) attribute payload and turn it into stored columns.
     *
     * @param  array<string, mixed>  $arguments
     * @param  string  $fallbackType  the type that applies when the payload doesn't state one
     * @return array<string, mixed>
     */
    protected static function attributePayload(array $arguments, bool $partial, string $fallbackType = 'text'): array
    {
        $req = $partial ? 'sometimes' : 'required';

        $data = Validator::make($arguments, [
            'name' => [$req, 'string', 'max:255'],
            'type' => [$req, Rule::in(array_keys(CategoryAttribute::TYPES))],
            'unit' => ['sometimes', 'nullable', 'string', 'max:32'],
            'options' => ['sometimes', 'array'],
            'options.*.label' => ['required', 'string', 'max:255'],
            'options.*.color' => ['sometimes', Rule::in(CategoryAttribute::OPTION_COLORS)],
        ])->validate();

        $type = $data['type'] ?? $fallbackType;
        $payload = [];

        if (isset($data['name'])) {
            $payload['name'] = trim($data['name']);
        }
        if (isset($data['type'])) {
            $payload['type'] = $type;
        }

        if ($type === 'select') {
            if (array_key_exists('options', $data) || ! $partial || isset($data['type'])) {
                $options = collect($data['options'] ?? [])
                    ->map(fn (array $o) => ['label' => trim($o['label']), 'color' => $o['color'] ?? CategoryAttribute::OPTION_COLORS[0]])
                    ->filter(fn (array $o) => $o['label'] !== '')
                    ->unique('label')
                    ->values()
                    ->all();

                if ($options === []) {
                    throw ValidationException::withMessages(['options' => 'A "select" attribute needs at least one option.']);
                }

                $payload['options'] = $options;
            }
        } elseif (isset($data['type'])) {
            $payload['options'] = null;
        }

        if (array_key_exists('unit', $data) || isset($data['type'])) {
            $unit = trim((string) ($data['unit'] ?? ''));
            $payload['unit'] = $type === 'number' && $unit !== '' ? $unit : null;
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    protected static function presentAttribute(CategoryAttribute $attr): array
    {
        return [
            'id' => $attr->id,
            'category_id' => $attr->event_category_id,
            'name' => $attr->name,
            'type' => $attr->type,
            'unit' => $attr->unit,
            'options' => $attr->optionsList(),
        ];
    }
}
