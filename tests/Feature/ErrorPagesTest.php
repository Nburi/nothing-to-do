<?php

namespace Tests\Feature;

use App\Models\ErrorOccurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unknown_url_renders_the_custom_404_page(): void
    {
        $response = $this->get('/this-route-does-not-exist');

        $response->assertNotFound();
        $response->assertSee('Diese Seite gibt es nicht.');
    }

    public function test_a_404_on_a_full_page_request_is_recorded(): void
    {
        $this->get('/this-route-does-not-exist');

        $occurrence = ErrorOccurrence::sole();
        $this->assertSame(404, $occurrence->status_code);
        $this->assertSame('/this-route-does-not-exist', $occurrence->path);
        $this->assertNull($occurrence->user_id);
    }

    public function test_a_404_on_a_json_request_is_not_recorded(): void
    {
        $this->getJson('/this-route-does-not-exist')->assertNotFound();

        $this->assertSame(0, ErrorOccurrence::count());
    }

    public function test_an_unexpected_exception_never_leaks_its_message_to_the_user(): void
    {
        config(['app.debug' => false]);
        Route::get('/__test-throw', fn () => throw new \RuntimeException('a secret internal detail'));

        $response = $this->get('/__test-throw');

        $response->assertServerError();
        $response->assertSee('Etwas ist schiefgelaufen.');
        $response->assertDontSee('a secret internal detail');
        $response->assertDontSee('RuntimeException');
    }

    public function test_an_unexpected_exception_is_recorded_with_its_class_and_message(): void
    {
        config(['app.debug' => false]);
        Route::get('/__test-throw', fn () => throw new \RuntimeException('a secret internal detail'));

        $this->get('/__test-throw');

        $occurrence = ErrorOccurrence::sole();
        $this->assertSame(500, $occurrence->status_code);
        $this->assertSame(\RuntimeException::class, $occurrence->exception_class);
        $this->assertSame('a secret internal detail', $occurrence->message);
    }

    public function test_a_403_renders_the_custom_page_and_is_recorded_with_the_authenticated_user(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get(route('admin.support'));

        $response->assertForbidden();
        $response->assertSee('Dafür fehlt dir der Zugriff.');

        $occurrence = ErrorOccurrence::sole();
        $this->assertSame(403, $occurrence->status_code);
        $this->assertSame($user->id, $occurrence->user_id);
    }

    public function test_the_back_link_points_home_for_a_guest_and_to_the_board_for_a_logged_in_user(): void
    {
        $this->get('/this-route-does-not-exist')->assertSee(route('home'), false);

        $user = User::factory()->create();
        $this->actingAs($user)->get('/this-route-does-not-exist')->assertSee(route('app'), false);
    }

    /**
     * Deliberately not a real `php artisan down`/`up` round trip: both write to and clear a real
     * file on disk (storage/framework/down), which every parallel test worker shares — an
     * artisan('down') call here left every *other* concurrently-running test in this class (and
     * presumably beyond) seeing a 503 too, discovered when this test was first written and every
     * sibling test in this file failed alongside it despite no code of theirs changing. Throwing
     * the exact exception PreventRequestsDuringMaintenance itself throws exercises the same view
     * -rendering path without touching shared global state.
     */
    public function test_maintenance_mode_renders_the_dedicated_page_with_a_reload_button(): void
    {
        Route::get('/__test-maintenance', fn () => throw new HttpException(503, 'Service Unavailable'));

        $response = $this->get('/__test-maintenance');

        $response->assertStatus(503);
        $response->assertSee('Wir sind gleich wieder da.');
        $response->assertSee('Seite neu laden');
        $response->assertDontSee('Etwas ist schiefgelaufen.');
    }

    public function test_maintenance_mode_is_not_recorded_as_an_error(): void
    {
        Route::get('/__test-maintenance', fn () => throw new HttpException(503, 'Service Unavailable'));

        $this->get('/__test-maintenance');

        $this->assertSame(0, ErrorOccurrence::count());
    }
}
