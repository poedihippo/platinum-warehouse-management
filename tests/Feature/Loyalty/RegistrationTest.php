<?php

namespace Tests\Feature\Loyalty;

use App\Mail\Loyalty\VerifyEmailMail;
use App\Models\Loyalty\LoyaltyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_verify_email_then_login_flow(): void
    {
        Mail::fake();

        // 1. Register
        $response = $this->postJson('/api/loyalty/auth/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('loyalty_users', ['email' => 'budi@example.com']);

        $user = LoyaltyUser::where('email', 'budi@example.com')->first();
        $this->assertNull($user->email_verified_at);
        Mail::assertSent(VerifyEmailMail::class);

        // 2. Verify email via the signed URL the listener would build
        $verifyUrl = URL::temporarySignedRoute(
            'loyalty.verification.verify',
            now()->addHours(24),
            ['id' => $user->getKey(), 'hash' => sha1($user->email)]
        );

        $this->getJson($verifyUrl)->assertOk();
        $this->assertNotNull($user->fresh()->email_verified_at);

        // 3. Login
        $login = $this->postJson('/api/loyalty/auth/login', [
            'email' => 'budi@example.com',
            'password' => 'secret123',
        ]);

        $login->assertOk()->assertJsonStructure(['token', 'token_type', 'data']);
    }

    public function test_verify_email_rejects_tampered_signature(): void
    {
        $user = LoyaltyUser::factory()->unverified()->create();

        // Unsigned URL -> 'signed' middleware blocks it.
        $this->getJson("/api/loyalty/auth/verify-email/{$user->getKey()}/" . sha1($user->email))
            ->assertStatus(403);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        LoyaltyUser::factory()->create(['email' => 'dup@example.com']);

        $this->postJson('/api/loyalty/auth/register', [
            'name' => 'Other',
            'email' => 'dup@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertStatus(422);
    }
}
