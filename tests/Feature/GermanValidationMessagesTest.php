<?php

namespace Tests\Feature;

use App\Livewire\WeekPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GermanValidationMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_validated_livewire_property_has_a_german_label(): void
    {
        $attributes = require lang_path('de/validation.php');
        $attributes = $attributes['attributes'];

        $missing = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Livewire')));

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            foreach (file($file->getPathname()) as $line) {
                // "'someProperty' => ['required', ...]" or "'someProperty' => [Rule::in(...)]"
                if (preg_match("/^\s+'([A-Za-z_.*]+)' => \[(?:'(?:required|nullable|sometimes|string|integer|date|boolean|array)|Rule::)/", $line, $m)
                    && ! array_key_exists($m[1], $attributes)) {
                    $missing[$m[1]] = $file->getFilename();
                }
            }
        }

        $this->assertSame([], $missing, 'Validated Livewire properties without a label in lang/de/validation.php attributes');
    }

    public function test_the_wochenplan_block_form_reports_german_field_names(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(WeekPlan::class)
            ->call('saveEventForm')
            ->assertHasErrors(['eventTitle', 'eventDays']);

        $errors = collect($component->errors()->all())->implode(' | ');

        $this->assertStringContainsString('Titel', $errors);
        $this->assertStringContainsString('Wochentage', $errors);
        $this->assertStringNotContainsString('event title', $errors);
        $this->assertStringNotContainsString('event days', $errors);
        $this->assertStringNotContainsString('field is required', $errors);
    }
}
