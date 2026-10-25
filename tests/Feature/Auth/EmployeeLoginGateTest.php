<?php

use App\Actions\Employee\RehireEmployee;
use App\Models\Employee;
use App\Models\User;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Features;
use Mockery\MockInterface;

test('active linked employees can log in', function () {
    $user = User::factory()->create();
    Employee::factory()->active()->forUser($user)->create();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('terminated linked employees cannot log in', function () {
    $user = User::factory()->create();
    Employee::factory()->terminated()->forUser($user)->create();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('users without a linked employee can log in', function () {
    $user = User::factory()->create();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('terminated employees are logged out on their next request', function () {
    $user = User::factory()->create();
    Employee::factory()->terminated()->forUser($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('rehired employees can log in again', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->terminated()->forUser($user)->create();
    $actor = User::factory()->create();

    app(RehireEmployee::class)->execute($employee, [
        'started_on' => now()->toDateString(),
        'start_reason' => 'Rehired',
    ], $actor);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('two-factor login rejects terminated employees after a valid code', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();
    Employee::factory()->terminated()->forUser($user)->create();

    $this->mock(TwoFactorAuthenticationProvider::class, function (MockInterface $mock): void {
        $mock->shouldReceive('verify')
            ->once()
            ->with('secret', '123456')
            ->andReturn(true);
    });

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('two-factor.login'));

    $this->post(route('two-factor.login.store'), [
        'code' => '123456',
    ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});
