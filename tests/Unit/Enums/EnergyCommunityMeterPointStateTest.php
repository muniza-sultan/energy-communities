<?php

namespace Tests\Unit\Enums;

use App\Enums\EnergyCommunityMeterPointState as S;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EnergyCommunityMeterPointStateTest extends TestCase
{
    /**
     * BR-9, verbatim. Every pair not listed here must be rejected.
     */
    private const ALLOWED = [
        'new' => ['requested', 'removed'],
        'requested' => ['message_received', 'error', 'removed'],
        'message_received' => ['accepted', 'error', 'removed'],
        'error' => ['requested', 'removed'],
        'accepted' => ['deactivated'],
        'removed' => [],
        'deactivated' => [],
    ];

    public static function allPairs(): iterable
    {
        foreach (S::cases() as $from) {
            foreach (S::cases() as $to) {
                yield "{$from->value} -> {$to->value}" => [$from, $to];
            }
        }
    }

    #[Test]
    #[DataProvider('allPairs')]
    public function it_allows_exactly_the_br9_transitions(S $from, S $to): void
    {
        $expected = in_array($to->value, self::ALLOWED[$from->value], true);

        $this->assertSame($expected, $from->canTransitionTo($to));
    }

    #[Test]
    public function blocking_states_match_br7(): void
    {
        $this->assertEqualsCanonicalizing(
            ['new', 'requested', 'message_received', 'accepted'],
            array_map(fn (S $s) => $s->value, S::blocking()),
        );
        $this->assertFalse(S::Error->isBlocking());
        $this->assertFalse(S::Removed->isBlocking());
        $this->assertFalse(S::Deactivated->isBlocking());
    }

    #[Test]
    public function only_removed_and_deactivated_are_terminal(): void
    {
        $terminal = array_filter(S::cases(), fn (S $s) => $s->isTerminal());

        $this->assertEqualsCanonicalizing([S::Removed, S::Deactivated], array_values($terminal));
    }

    #[Test]
    public function end_state_follows_br10(): void
    {
        $this->assertSame(S::Deactivated, S::Accepted->endState());

        foreach ([S::New, S::Requested, S::MessageReceived, S::Error] as $state) {
            $this->assertSame(S::Removed, $state->endState(), $state->value);
        }

        $this->assertNull(S::Removed->endState());
        $this->assertNull(S::Deactivated->endState());
    }
}
