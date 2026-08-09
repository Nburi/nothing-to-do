<?php

namespace Tests\Feature;

use App\Livewire\CraftIdeas;
use App\Models\CraftIdea;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CraftIdeasTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_suggests_a_hero_idea_when_ideas_exist(): void
    {
        $user = User::factory()->create();
        CraftIdea::factory()->for($user)->count(3)->create();

        $component = Livewire::actingAs($user)->test(CraftIdeas::class);

        $this->assertNotNull($component->instance()->heroId);
    }

    public function test_there_is_no_hero_when_no_ideas_exist(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(CraftIdeas::class);

        $this->assertNull($component->instance()->heroId);
    }

    public function test_shuffle_swaps_to_the_other_open_idea_when_exactly_two_exist(): void
    {
        $user = User::factory()->create();
        $a = CraftIdea::factory()->for($user)->create();
        $b = CraftIdea::factory()->for($user)->create();

        $component = Livewire::actingAs($user)->test(CraftIdeas::class);
        $firstHero = $component->instance()->heroId;
        $other = $firstHero === $a->id ? $b->id : $a->id;

        $component->call('shuffle');

        $this->assertSame($other, $component->instance()->heroId);
    }

    public function test_promoting_an_idea_makes_it_the_hero(): void
    {
        $user = User::factory()->create();
        CraftIdea::factory()->for($user)->count(2)->create();
        $target = CraftIdea::factory()->for($user)->create(['title' => 'PROMOTE-ME']);

        Livewire::actingAs($user)
            ->test(CraftIdeas::class)
            ->call('promote', $target->id)
            ->assertSee('PROMOTE-ME');
    }

    public function test_marking_the_hero_done_automatically_rerolls_to_another_open_idea(): void
    {
        $user = User::factory()->create();
        $a = CraftIdea::factory()->for($user)->create();
        $b = CraftIdea::factory()->for($user)->create();

        $component = Livewire::actingAs($user)->test(CraftIdeas::class);
        $heroId = $component->instance()->heroId;

        $component->call('markDone', $heroId);

        $this->assertDatabaseHas('craft_ideas', ['id' => $heroId, 'is_done' => true]);
        $newHero = $component->instance()->heroId;
        $this->assertNotNull($newHero);
        $this->assertNotSame($heroId, $newHero);
        $this->assertContains($newHero, [$a->id, $b->id]);
    }

    public function test_a_done_idea_can_be_restored(): void
    {
        $user = User::factory()->create();
        $idea = CraftIdea::factory()->for($user)->done()->create();

        Livewire::actingAs($user)
            ->test(CraftIdeas::class)
            ->call('restoreIdea', $idea->id);

        $this->assertDatabaseHas('craft_ideas', ['id' => $idea->id, 'is_done' => false]);
    }

    public function test_deleting_the_hero_rerolls_to_another_open_idea(): void
    {
        $user = User::factory()->create();
        $a = CraftIdea::factory()->for($user)->create();
        $b = CraftIdea::factory()->for($user)->create();

        $component = Livewire::actingAs($user)->test(CraftIdeas::class);
        $heroId = $component->instance()->heroId;

        $component->call('deleteIdea', $heroId);

        $this->assertDatabaseMissing('craft_ideas', ['id' => $heroId]);
        $remaining = $heroId === $a->id ? $b->id : $a->id;
        $this->assertSame($remaining, $component->instance()->heroId);
    }

    public function test_a_user_cannot_see_or_mutate_another_users_ideas(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreignIdea = CraftIdea::factory()->for($other)->create();

        $component = Livewire::actingAs($user)->test(CraftIdeas::class);

        try {
            $component->call('markDone', $foreignIdea->id);
            $this->fail('Expected a ModelNotFoundException for the foreign idea.');
        } catch (ModelNotFoundException) {
            // The foreign idea is invisible through the owner relationship.
        }

        try {
            $component->call('promote', $foreignIdea->id);
            $this->fail('Expected a ModelNotFoundException promoting a foreign idea.');
        } catch (ModelNotFoundException) {
            // Same guard on promote(), scoped through auth()->user()->craftIdeas().
        }

        $this->assertDatabaseHas('craft_ideas', ['id' => $foreignIdea->id, 'is_done' => false]);
    }

    public function test_promoting_a_done_idea_is_rejected(): void
    {
        $user = User::factory()->create();
        $doneIdea = CraftIdea::factory()->for($user)->done()->create();

        try {
            Livewire::actingAs($user)
                ->test(CraftIdeas::class)
                ->call('promote', $doneIdea->id);
            $this->fail('Expected a ModelNotFoundException promoting a done idea.');
        } catch (ModelNotFoundException) {
            // promote() is scoped through open(), so a done idea can't become the hero.
        }

        $this->assertDatabaseHas('craft_ideas', ['id' => $doneIdea->id, 'is_done' => true]);
    }

    public function test_other_ideas_excludes_the_hero_and_done_ideas(): void
    {
        $user = User::factory()->create();
        CraftIdea::factory()->for($user)->done()->create(['title' => 'DONE-ONE']);
        CraftIdea::factory()->for($user)->create(['title' => 'OPEN-ONE']);
        CraftIdea::factory()->for($user)->create(['title' => 'OPEN-TWO']);

        $component = Livewire::actingAs($user)->test(CraftIdeas::class);
        $heroTitle = $component->instance()->hero->title;
        $otherTitles = $component->instance()->otherIdeas->pluck('title')->all();

        $this->assertNotContains($heroTitle, $otherTitles);
        $this->assertNotContains('DONE-ONE', $otherTitles);
    }
}
