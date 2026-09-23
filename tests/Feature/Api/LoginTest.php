<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_a_token_for_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertOk()->assertJsonStructure(['token', 'token_type']);

        $this->assertSame(1, $user->tokens()->count());
    }

    #[Test]
    public function it_rejects_invalid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    #[Test]
    public function the_token_authenticates_api_requests(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->json('token');

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonMissingPath('password');
    }

    #[Test]
    public function it_returns_json_401_without_a_token(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }
}
