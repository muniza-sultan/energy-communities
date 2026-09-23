<?php

namespace Tests\Feature\Actions;

use App\Actions\CreateEnergyCommunity;
use App\Models\EnergyCommunity;
use App\Models\EnergyCommunityUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class CreateEnergyCommunityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function nothing_is_written_if_the_manager_row_fails(): void
    {
        // BR-3: community + manager row are one atomic operation.
        EnergyCommunityUser::creating(fn () => throw new RuntimeException('simulated failure'));

        try {
            app(CreateEnergyCommunity::class)->handle(User::factory()->create(), ['ecid' => 'AT-TEST-1']);
            $this->fail('Expected the simulated failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('simulated failure', $e->getMessage());
        }

        $this->assertSame(0, EnergyCommunity::withTrashed()->count());
    }
}
