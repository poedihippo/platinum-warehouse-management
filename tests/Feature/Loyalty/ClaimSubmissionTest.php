<?php

namespace Tests\Feature\Loyalty;

use App\Models\Loyalty\Claim;
use App\Models\Loyalty\LoyaltyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClaimSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.default' => 'local']);
        Storage::fake('local');
    }

    private function actingAsLoyalty(?LoyaltyUser $user = null): LoyaltyUser
    {
        $user ??= LoyaltyUser::factory()->create();
        Sanctum::actingAs($user, ['loyalty'], 'loyalty');

        return $user;
    }

    private function payload(string $invoice = 'INV-2026-04-1234'): array
    {
        return [
            'invoice_number' => $invoice,
            'invoice_photo' => UploadedFile::fake()->image('invoice.jpg'),
            'product_photos' => [
                UploadedFile::fake()->image('p1.jpg'),
                UploadedFile::fake()->image('p2.jpg'),
            ],
        ];
    }

    public function test_submit_claim_with_valid_data(): void
    {
        $user = $this->actingAsLoyalty();

        $response = $this->postJson('/api/loyalty/claims', $this->payload());

        $response->assertStatus(201);

        $claim = Claim::where('loyalty_user_id', $user->getKey())->first();
        $this->assertNotNull($claim);
        $this->assertSame('pending', $claim->status);
        $this->assertSame('INV-2026-04-1234', $claim->invoice_number);
        $this->assertCount(2, $claim->photos);

        // Stored under the claim ULID.
        $this->assertStringContainsString("loyalty/claims/{$claim->id}/", $claim->invoice_photo_path);
        Storage::disk('local')->assertExists($claim->invoice_photo_path);
    }

    public function test_unverified_user_cannot_submit(): void
    {
        $this->actingAsLoyalty(LoyaltyUser::factory()->unverified()->create());

        $this->postJson('/api/loyalty/claims', $this->payload())
            ->assertStatus(403);
    }

    public function test_duplicate_invoice_number_per_user_is_rejected(): void
    {
        $user = $this->actingAsLoyalty();

        $this->postJson('/api/loyalty/claims', $this->payload('INV-DUP-1'))
            ->assertStatus(201);

        $this->postJson('/api/loyalty/claims', $this->payload('INV-DUP-1'))
            ->assertStatus(422)
            ->assertJson(['message' => 'Invoice ini sudah pernah Anda submit.']);
    }

    public function test_rate_limit_blocks_sixth_submission_in_a_day(): void
    {
        $this->actingAsLoyalty();

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/loyalty/claims', $this->payload("INV-RL-{$i}"))
                ->assertStatus(201);
        }

        // 6th within the same day -> throttled.
        $this->postJson('/api/loyalty/claims', $this->payload('INV-RL-6'))
            ->assertStatus(429);
    }
}
