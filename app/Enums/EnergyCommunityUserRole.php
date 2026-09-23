<?php

namespace App\Enums;

/**
 * energy_community_user.role. Not one of the three enums named in the brief,
 * so this name is ours.
 */
enum EnergyCommunityUserRole: string
{
    case Manager = 'manager';
    case Member = 'member';
}
