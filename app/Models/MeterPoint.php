<?php

namespace App\Models;

use App\Enums\EnergyDirection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MeterPoint extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'user_id', 'energy_direction', 'grid_operator_id'];

    protected function casts(): array
    {
        return [
            'energy_direction' => EnergyDirection::class,
        ];
    }

    /**
     * BR-1: the first 8 characters of the code identify the grid operator.
     */
    public static function gridOperatorIdentifierFrom(string $code): string
    {
        return substr($code, 0, 8);
    }

    /**
     * exactly one owner.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * grid_operator_id holds grid_operators.identifier.
     *
     * @return BelongsTo<GridOperator, $this>
     */
    public function gridOperator(): BelongsTo
    {
        return $this->belongsTo(GridOperator::class, 'grid_operator_id', 'identifier');
    }

    /**
     * @return HasMany<EnergyCommunityMeterPoint, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(EnergyCommunityMeterPoint::class);
    }

    /**
     * BR-2 / BR-11 (metering-point endpoints): owner or admin only.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->is_admin) {
            return;
        }

        $query->where('user_id', $user->id);
    }
}
