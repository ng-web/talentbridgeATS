<?php

namespace Tests\Feature\Auth;

use App\Models\JobSeeker;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        Mail::fake();
        $this->seed(RolesAndPermissionsSeeder::class);

        $program = Program::create([
            'name' => 'Summer Work & Travel',
            'slug' => 'summer-work-travel',
            'is_active' => true,
        ]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'job_seeker',
            'program' => $program->slug,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('job_seekers', [
            'user_id' => auth()->id(),
            'program_id' => $program->id,
        ]);
    }

    public function test_program_card_preselects_registration_program(): void
    {
        $program = Program::create([
            'name' => 'Au Pair',
            'slug' => 'au-pair',
            'is_active' => true,
        ]);

        $this->get(route('register', ['program' => $program->slug]))
            ->assertOk()
            ->assertSee('value="au-pair"', false)
            ->assertSee('selected', false);
    }

    public function test_registration_rejects_an_inactive_program(): void
    {
        Mail::fake();
        $this->seed(RolesAndPermissionsSeeder::class);

        Program::create([
            'name' => 'Inactive Program',
            'slug' => 'inactive-program',
            'is_active' => false,
        ]);

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'invalid-program@example.com',
            'role' => 'job_seeker',
            'program' => 'inactive-program',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('program');

        $this->assertDatabaseMissing('users', ['email' => 'invalid-program@example.com']);
    }

    public function test_registration_rejects_a_genuinely_invalid_program_slug(): void
    {
        Mail::fake();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->post('/register', [
            'name' => 'Invalid Slug User',
            'email' => 'invalid-slug@example.com',
            'role' => 'job_seeker',
            'program' => 'program-that-does-not-exist',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('program');

        $this->assertDatabaseMissing('users', ['email' => 'invalid-slug@example.com']);
    }

    public function test_legacy_applicant_can_select_program_once_from_profile(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $first = Program::create(['name' => 'Au Pair', 'slug' => 'au-pair', 'is_active' => true]);
        $second = Program::create(['name' => 'Camp Counselor', 'slug' => 'camp-counselor', 'is_active' => true]);
        $user = User::factory()->create();
        $user->assignRole('job_seeker');
        JobSeeker::create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('jobseeker.profile.edit'))
            ->assertOk()
            ->assertSee('Select a Program');

        $this->patch(route('jobseeker.profile.update'), ['program_id' => $first->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('job_seekers', ['user_id' => $user->id, 'program_id' => $first->id]);

        $this->patch(route('jobseeker.profile.update'), ['program_id' => $second->id])
            ->assertSessionHasErrors('program_id');

        $this->assertDatabaseHas('job_seekers', ['user_id' => $user->id, 'program_id' => $first->id]);
    }
}
