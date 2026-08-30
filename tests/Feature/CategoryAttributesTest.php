<?php

namespace Tests\Feature;

use App\Livewire\Settings;
use App\Models\CategoryAttribute;
use App\Models\EventCategory;
use App\Models\ScheduleEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryAttributesTest extends TestCase
{
    use RefreshDatabase;

    public function test_manage_attributes_opens_the_sheet_for_the_given_category(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create(['name' => 'Training']);

        $component = Livewire::actingAs($user)->test(Settings::class)->call('manageAttributes', $category->id);

        $this->assertSame($category->id, $component->get('managingAttributesCategoryId'));
        $component->assertSeeHtml('Attribute für „Training“');
    }

    public function test_manage_attributes_on_a_foreign_category_is_rejected(): void
    {
        $user = User::factory()->create();
        $foreign = EventCategory::factory()->for(User::factory())->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($user)->test(Settings::class)->call('manageAttributes', $foreign->id);
    }

    public function test_save_attribute_creates_a_text_attribute(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create();

        Livewire::actingAs($user)->test(Settings::class)
            ->call('manageAttributes', $category->id)
            ->set('attrName', 'Notizen')
            ->set('attrType', 'text')
            ->call('saveAttribute')
            ->assertHasNoErrors();

        $attribute = $category->customAttributes()->sole();
        $this->assertSame('Notizen', $attribute->name);
        $this->assertSame('text', $attribute->type);
        $this->assertNull($attribute->options);
    }

    public function test_save_attribute_creates_a_number_attribute_with_a_unit(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create();

        Livewire::actingAs($user)->test(Settings::class)
            ->call('manageAttributes', $category->id)
            ->set('attrName', 'Dauer')
            ->set('attrType', 'number')
            ->set('attrUnit', 'Min')
            ->call('saveAttribute')
            ->assertHasNoErrors();

        $attribute = $category->customAttributes()->sole();
        $this->assertSame('number', $attribute->type);
        $this->assertSame('Min', $attribute->unit);
    }

    public function test_save_attribute_creates_a_select_attribute_with_coloured_options(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create();

        Livewire::actingAs($user)->test(Settings::class)
            ->call('manageAttributes', $category->id)
            ->set('attrName', 'Trainingstyp')
            ->set('attrType', 'select')
            ->set('attrOptions', [
                ['label' => 'Lauf', 'color' => 'forest'],
                ['label' => 'Kraft', 'color' => 'signal'],
            ])
            ->call('saveAttribute')
            ->assertHasNoErrors();

        $attribute = $category->customAttributes()->sole();
        $this->assertSame('select', $attribute->type);
        $this->assertSame([
            ['label' => 'Lauf', 'color' => 'forest'],
            ['label' => 'Kraft', 'color' => 'signal'],
        ], $attribute->options);
    }

    public function test_save_attribute_rejects_a_select_type_with_no_options(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create();

        Livewire::actingAs($user)->test(Settings::class)
            ->call('manageAttributes', $category->id)
            ->set('attrName', 'Trainingstyp')
            ->set('attrType', 'select')
            ->set('attrOptions', [['label' => '', 'color' => 'forest']])
            ->call('saveAttribute')
            ->assertHasErrors('attrOptions');

        $this->assertSame(0, $category->customAttributes()->count());
    }

    public function test_save_attribute_deduplicates_option_labels(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create();

        Livewire::actingAs($user)->test(Settings::class)
            ->call('manageAttributes', $category->id)
            ->set('attrName', 'Trainingstyp')
            ->set('attrType', 'select')
            ->set('attrOptions', [
                ['label' => 'Lauf', 'color' => 'forest'],
                ['label' => 'Lauf', 'color' => 'signal'],
            ])
            ->call('saveAttribute')
            ->assertHasNoErrors();

        $attribute = $category->customAttributes()->sole();
        $this->assertCount(1, $attribute->options);
    }

    public function test_start_edit_attribute_seeds_the_form_and_save_updates_in_place(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create();
        $attribute = $category->customAttributes()->create(['name' => 'Dauer', 'type' => 'number', 'unit' => 'Min', 'sort_order' => 0]);

        Livewire::actingAs($user)->test(Settings::class)
            ->call('manageAttributes', $category->id)
            ->call('startEditAttribute', $attribute->id)
            ->assertSet('attrName', 'Dauer')
            ->assertSet('attrType', 'number')
            ->assertSet('attrUnit', 'Min')
            ->set('attrName', 'Trainingsdauer')
            ->call('saveAttribute')
            ->assertHasNoErrors();

        $this->assertSame(1, $category->customAttributes()->count());
        $this->assertSame('Trainingsdauer', $attribute->fresh()->name);
    }

    public function test_delete_attribute_removes_it_and_its_stored_values(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create();
        $attribute = $category->customAttributes()->create(['name' => 'Dauer', 'type' => 'number', 'unit' => 'Min', 'sort_order' => 0]);
        $event = ScheduleEvent::factory()->for($user)->create(['category_id' => $category->id]);
        $event->attributeValues()->attach($attribute->id, ['value' => '60']);

        Livewire::actingAs($user)->test(Settings::class)
            ->call('manageAttributes', $category->id)
            ->call('deleteAttribute', $attribute->id);

        $this->assertSame(0, $category->customAttributes()->count());
        $this->assertSame(0, $event->attributeValues()->count());
    }

    public function test_deleting_the_category_cascades_to_its_attributes_and_values(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create();
        $attribute = $category->customAttributes()->create(['name' => 'Dauer', 'type' => 'number', 'sort_order' => 0]);
        $event = ScheduleEvent::factory()->for($user)->create(['category_id' => $category->id]);
        $event->attributeValues()->attach($attribute->id, ['value' => '60']);

        $category->delete();

        $this->assertSame(0, CategoryAttribute::query()->count());
        $this->assertSame(0, $event->attributeValues()->count());
    }

    public function test_the_categories_list_shows_an_attribute_count_pill(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create(['name' => 'Training']);
        $category->customAttributes()->create(['name' => 'Dauer', 'type' => 'number', 'sort_order' => 0]);

        $html = Livewire::actingAs($user)->test(Settings::class)->html();

        $this->assertStringContainsString('1 Attribut', $html);
    }
}
