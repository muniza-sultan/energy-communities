<?php

namespace App\Models;

use App\Enums\EnergyCommunityUserRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;


class EnergyCommunityUser extends Pivot
{
    protected $table = 'energy_community_user';

    public $incrementing = true;

    protected $fillable = ['energy_community_id', 'user_id', 'role'];

    protected function casts(): array
    {
        return [
            'role' => EnergyCommunityUserRole::class,
        ];
    }

    /**
     * @return BelongsTo<EnergyCommunity, $this>
     */
    public function energyCommunity(): BelongsTo
    {
        return $this->belongsTo(EnergyCommunity::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
