<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\URL;
use App\Models\User;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function testRegistration()
    {
        Notification::fake();

        $email = 'user'.uniqid().'@example.com';
        $password = 'Password123!';

        $response = $this->postJson(route('register'), [
            'name' => 'Test User',
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['user', 'token']);

        $this->assertDatabaseHas('users', ['email' => $email]);

        $user = User::where('email', $email)->first();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function testLogin()
    {
        $password = 'Secret123!';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        $response = $this->postJson(route('login'), [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['user', 'token']);
    }

    public function testEmailVerification()
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->getJson($verificationUrl);

        $response->assertStatus(200);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function testPasswordReset()
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->postJson(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertStatus(200);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $token = $notification->token;
            $newPassword = 'NewPass123!';

            $response = $this->postJson(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]);

            $response->assertStatus(200);
            $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));

            return true;
        });
    }
}