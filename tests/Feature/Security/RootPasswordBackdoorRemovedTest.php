<?php

namespace Tests\Feature\Security;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RootPasswordBackdoorRemovedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The old backdoor let any request authenticate as an existing user by
     * sending the ROOT_PASSWORD env value as the password. Even with that env
     * var set, the value must now be treated as just another wrong password.
     */
    public function test_root_password_does_not_grant_token(): void
    {
        putenv('ROOT_PASSWORD=super-secret-test-value');

        $user = User::factory()->create([
            'email' => 'realuser@example.com',
            'password' => 'the-actual-password',
            'type' => UserType::Customer,
        ]);

        $response = $this->postJson('/api/auth/token', [
            'email' => $user->email,
            'password' => 'super-secret-test-value',
        ]);

        // Should fail like any wrong credential, not issue a token.
        $this->assertNotEquals(200, $response->status());
        $this->assertNotEquals(201, $response->status());
        $response->assertStatus(422);

        putenv('ROOT_PASSWORD');
    }

    /**
     * Positive control: removing the backdoor must not break normal login.
     */
    public function test_valid_credentials_still_issue_token(): void
    {
        $user = User::factory()->create([
            'email' => 'validuser@example.com',
            'password' => 'the-actual-password',
            'type' => UserType::Customer,
        ]);

        $response = $this->postJson('/api/auth/token', [
            'email' => $user->email,
            'password' => 'the-actual-password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.token', fn ($token) => is_string($token) && $token !== '');
    }
}
