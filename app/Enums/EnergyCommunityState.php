<?php

namespace App\Enums;

enum EnergyCommunityState: string
{
    case New = 'new';
    case Activated = 'activated';
    case Rejected = 'rejected';

    /**
     * BR-5: metering points may be registered while new or activated, never when rejected.
     * Decision (NOTES): the same applies to adding users.
     */
    public function acceptsChanges(): bool
    {
        return $this !== self::Rejected;
    }
}
