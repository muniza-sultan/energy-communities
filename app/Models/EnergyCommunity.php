<?php

namespace App\Models;

use App\Enums\EnergyCommunityState;
use App\Enums\EnergyCommunityUserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EnergyCommunity extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['ecid', 'name', 'state'];

    protected function casts(): array
    {
        return [
            'state' => EnergyCommunityState::class,
        ];
    }

    /**
     * @return HasMany<EnergyCommunityUser, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(EnergyCommunityUser::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(EnergyCommunityUser::class)
            ->withPivot(['id', 'role'])
            ->withTimestamps();
    }

    /**
     * The registrations (energy_community_meter_point rows) of this community.
     *
     * @return HasMany<EnergyCommunityMeterPoint, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(EnergyCommunityMeterPoint::class);
    }

    public function roleOf(User $user): ?EnergyCommunityUserRole
    {
        return $this->memberships()->where('user_id', $user->id)->first()?->role;
    }

    /**
     * BR-4: "member" = has a row in energy_community_user, whatever the role.
     */
    public function hasMember(User $user): bool
    {
        return $this->memberships()->where('user_id', $user->id)->exists();
    }

    public function hasManager(User $user): bool
    {
        return $this->roleOf($user) === EnergyCommunityUserRole::Manager;
    }

    /**
     * BR-11: admins see everything, everyone else only communities they belong to.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->is_admin) {
            return;
        }

        $query->whereHas('memberships', fn (Builder $q) => $q->where('user_id', $user->id));
    }
}
