<?php

namespace App\Enums;

/**
 * State of a registration (energy_community_meter_point).
 * The whole BR-9 state machine lives here so it is defined in exactly one place.
 */
enum EnergyCommunityMeterPointState: string
{
    case New = 'new';
    case Requested = 'requested';
    case MessageReceived = 'message_received';
    case Accepted = 'accepted';
    case Error = 'error';
    case Removed = 'removed';
    case Deactivated = 'deactivated';

    /**
     * BR-7: states that block an overlapping registration of the same metering point.
     * Keep in sync with the exclusion constraint in the registrations migration.
     *
     * @return list<self>
     */
    public static function blocking(): array
    {
        return [self::New, self::Requested, self::MessageReceived, self::Accepted];
    }

    public function isBlocking(): bool
    {
        return in_array($this, self::blocking(), true);
    }

    /**
     * BR-9: deactivated and removed are terminal.
     */
    public function isTerminal(): bool
    {
        return $this === self::Removed || $this === self::Deactivated;
    }

    /**
     * BR-9: allowed transitions out of this state.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Requested, self::Removed],
            self::Requested => [self::MessageReceived, self::Error, self::Removed],
            self::MessageReceived => [self::Accepted, self::Error, self::Removed],
            self::Error => [self::Requested, self::Removed],
            self::Accepted => [self::Deactivated],
            self::Removed, self::Deactivated => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * BR-10 / BR-13: the transition that "ends" a registration in this state,
     * or null if it has already ended.
     */
    public function endState(): ?self
    {
        return match (true) {
            $this->canTransitionTo(self::Deactivated) => self::Deactivated,
            $this->canTransitionTo(self::Removed) => self::Removed,
            default => null,
        };
    }
}
