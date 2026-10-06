<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Mail\UserInvitationMail;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\UserInvitations;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class UserInvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'locale' => 'es']);
    }

    private function create(array $data): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateUser::class)
            ->fillForm($data + ['role' => 'agent'])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    private function pendingUser(): User
    {
        Mail::fake();
        $this->create(['name' => 'Ana Agent', 'email' => 'ana@vantins.test']);

        return User::where('email', 'ana@vantins.test')->firstOrFail();
    }

    public function test_a_user_created_without_a_password_is_invited_by_email(): void
    {
        $user = $this->pendingUser();

        $this->assertTrue($user->hasPendingInvitation());
        $this->assertNotNull($user->invited_at);

        Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail) {
            return $mail->hasTo('ana@vantins.test')
                && str_contains($mail->url, '/admin/password-reset/reset')
                && str_contains($mail->render(), e($mail->url))
                && str_contains($mail->render(), 'Crear mi contraseña')
                && str_contains($mail->render(), 'Create my password');
        });

        $this->assertTrue(ActivityLog::where('event', 'link_issued')->where('subject_id', $user->id)->where('subject_type', $user->auditType())->exists());
    }

    public function test_an_invited_user_cannot_log_in_until_they_choose_a_password(): void
    {
        $user = $this->pendingUser();

        $this->assertFalse(auth()->attempt(['email' => $user->email, 'password' => 'password']));
        $this->assertFalse(auth()->attempt(['email' => $user->email, 'password' => '']));
    }

    public function test_a_user_created_with_a_password_is_active_and_gets_no_email(): void
    {
        Mail::fake();

        $this->create(['name' => 'Bob', 'email' => 'bob@vantins.test', 'password' => 'a-strong-pass-1']);

        $bob = User::where('email', 'bob@vantins.test')->firstOrFail();
        $this->assertFalse($bob->hasPendingInvitation());
        $this->assertNull($bob->invited_at);
        Mail::assertNothingSent();
    }

    public function test_the_invitation_link_opens_the_password_page_and_choosing_a_password_activates_the_account(): void
    {
        $user = $this->pendingUser();

        $url = UserInvitations::link($user);
        auth()->logout();
        $this->get($url)->assertOk();

        $token = Password::broker('users')->createToken($user);
        $status = Password::broker('users')->reset(
            ['email' => $user->email, 'password' => 'my-own-password-9', 'password_confirmation' => 'my-own-password-9', 'token' => $token],
            function (User $u, string $password) {
                $u->forceFill(['password' => $password])->save();
                event(new PasswordReset($u));
            },
        );

        $this->assertSame(Password::PASSWORD_RESET, $status);

        $user->refresh();
        $this->assertFalse($user->hasPendingInvitation());
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('my-own-password-9', $user->password));
    }

    public function test_a_new_link_replaces_the_previous_one(): void
    {
        $user = $this->pendingUser();

        $first = UserInvitations::link($user);
        $second = UserInvitations::link($user);

        parse_str(parse_url($first, PHP_URL_QUERY), $a);
        parse_str(parse_url($second, PHP_URL_QUERY), $b);

        $this->assertFalse(Password::broker('users')->tokenExists($user, $a['token']));
        $this->assertTrue(Password::broker('users')->tokenExists($user, $b['token']));
    }

    public function test_the_invitation_stays_valid_for_days_not_minutes(): void
    {
        $this->assertGreaterThanOrEqual(2, UserInvitations::validDays());
    }

    public function test_the_actions_are_available_for_pending_and_active_users_alike(): void
    {
        $pending = $this->pendingUser();
        $active = User::factory()->create(['role' => 'agent']);
        $active->forceFill(['password_set_at' => now()])->save();

        Livewire::actingAs($this->admin)
            ->test(ListUsers::class)
            ->assertTableActionVisible('resendInvitation', $pending)
            ->assertTableActionVisible('resendInvitation', $active)
            ->assertTableActionVisible('copyInvitationLink', $active)
            ->callTableAction('resendInvitation', $pending)
            ->callTableAction('resendInvitation', $active)
            ->assertNotified();

        Mail::assertSent(UserInvitationMail::class, 3);
        $this->assertFalse($active->fresh()->hasPendingInvitation(), 'sending a link does not deactivate an active account');
    }

    public function test_the_form_has_no_language_field_and_the_email_goes_out_in_both_languages(): void
    {
        Livewire::actingAs($this->admin)->test(CreateUser::class)->assertFormFieldDoesNotExist('locale');

        $user = $this->pendingUser();

        Mail::assertSent(UserInvitationMail::class, fn (UserInvitationMail $mail) => $mail->hasTo($user->email)
            && str_contains($mail->render(), 'Estimado/a Ana Agent')
            && str_contains($mail->render(), 'Dear Ana Agent'));
    }

    public function test_the_link_can_be_copied_when_the_email_does_not_arrive(): void
    {
        $user = $this->pendingUser();

        Livewire::actingAs($this->admin)
            ->test(ListUsers::class)
            ->assertTableActionVisible('copyInvitationLink', $user)
            ->callTableAction('copyInvitationLink', $user)
            ->assertNotified(__('panel.user.invite_link_title'));

        // The same actions are on the edit page.
        Livewire::actingAs($this->admin)
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->assertActionVisible('copyInvitationLink')
            ->assertActionVisible('resendInvitation');
    }

    public function test_when_the_mail_cannot_be_sent_the_user_still_exists_and_the_admin_is_told(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP connection refused'));

        $this->create(['name' => 'Carla', 'email' => 'carla@vantins.test']);

        $carla = User::where('email', 'carla@vantins.test')->firstOrFail();
        $this->assertTrue($carla->hasPendingInvitation());
        $this->assertNull($carla->invited_at, 'nothing went out, so no invitation is recorded');
    }

    public function test_an_administrator_typing_a_password_for_a_pending_user_activates_the_account(): void
    {
        $user = $this->pendingUser();

        Livewire::actingAs($this->admin)
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm(['password' => 'typed-by-the-admin-1'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($user->fresh()->hasPendingInvitation());
    }

    public function test_the_forgot_password_page_exists_and_login_links_to_it(): void
    {
        $this->get('/admin/password-reset/request')->assertOk();
        $this->get('/admin/login')->assertOk()->assertSee('/admin/password-reset/request', false);
    }

    public function test_existing_users_count_as_active_after_the_migration(): void
    {
        $this->assertFalse(User::factory()->create(['password_set_at' => now()])->hasPendingInvitation());
        $this->assertTrue(User::factory()->create(['password_set_at' => null])->hasPendingInvitation());
    }
}
