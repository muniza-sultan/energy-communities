<?php

namespace Tests\Feature\Policies;

use App\Models\EnergyCommunity;
use App\Models\EnergyCommunityMeterPoint;
use App\Models\MeterPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $member;
    private User $outsider;
    private User $admin;
    private EnergyCommunity $community;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create();
        $this->member = User::factory()->create();
        $this->outsider = User::factory()->create();
        $this->admin = User::factory()->admin()->create();

        $this->community = EnergyCommunity::factory()
            ->withManager($this->manager)
            ->withMember($this->member)
            ->create();
    }

    private function allows(User $user, string $ability, mixed $arguments): bool
    {
        return Gate::forUser($user)->allows($ability, $arguments);
    }

    #[Test]
    public function only_the_owner_and_admins_may_view_a_meter_point(): void
    {
        $meterPoint = MeterPoint::factory()->ownedBy($this->member)->create();

        $this->assertTrue($this->allows($this->member, 'view', $meterPoint));
        $this->assertTrue($this->allows($this->admin, 'view', $meterPoint));
        $this->assertFalse($this->allows($this->manager, 'view', $meterPoint));
        $this->assertFalse($this->allows($this->outsider, 'view', $meterPoint));
    }

    #[Test]
    public function members_of_either_role_and_admins_may_view_a_community(): void
    {
        $this->assertTrue($this->allows($this->manager, 'view', $this->community));
        $this->assertTrue($this->allows($this->member, 'view', $this->community));
        $this->assertTrue($this->allows($this->admin, 'view', $this->community));
        $this->assertFalse($this->allows($this->outsider, 'view', $this->community));
    }

    #[Test]
    public function only_managers_and_admins_may_manage_a_community(): void
    {
        foreach (['addUser', 'registerMeterPoint', 'activate', 'reject'] as $ability) {
            $this->assertTrue($this->allows($this->manager, $ability, $this->community), $ability);
            $this->assertTrue($this->allows($this->admin, $ability, $this->community), $ability);
            $this->assertFalse($this->allows($this->member, $ability, $this->community), $ability);
            $this->assertFalse($this->allows($this->outsider, $ability, $this->community), $ability);
        }
    }

    #[Test]
    public function registration_permissions_follow_the_community(): void
    {
        $registration = EnergyCommunityMeterPoint::factory()
            ->for($this->community)
            ->create();

        foreach (['transition', 'delete'] as $ability) {
            $this->assertTrue($this->allows($this->manager, $ability, $registration), $ability);
            $this->assertTrue($this->allows($this->admin, $ability, $registration), $ability);
            $this->assertFalse($this->allows($this->member, $ability, $registration), $ability);
            $this->assertFalse($this->allows($this->outsider, $ability, $registration), $ability);
        }

        $this->assertTrue($this->allows($this->member, 'view', $registration));
        $this->assertFalse($this->allows($this->outsider, 'view', $registration));
    }

    #[Test]
    public function a_manager_of_another_community_has_no_rights_here(): void
    {
        $otherManager = User::factory()->create();
        EnergyCommunity::factory()->withManager($otherManager)->create();

        $this->assertFalse($this->allows($otherManager, 'view', $this->community));
        $this->assertFalse($this->allows($otherManager, 'addUser', $this->community));
    }
}
