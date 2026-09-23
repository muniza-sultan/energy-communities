<?php

namespace App\Models;

use App\Enums\EnergyCommunityMeterPointState;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class EnergyCommunityMeterPoint extends Model
{
    use HasFactory;

    protected $table = 'energy_community_meter_point';

    protected $fillable = [
        'energy_community_id',
        'meter_point_id',
        'state',
        'from_date',
        'to_date',
        'consent_date',
        'status_code',
    ];

    protected function casts(): array
    {
        return [
            'state' => EnergyCommunityMeterPointState::class,
            'from_date' => 'date:Y-m-d',
            'to_date' => 'date:Y-m-d',
            'consent_date' => 'date:Y-m-d',
            'status_code' => 'integer',
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
     * @return BelongsTo<MeterPoint, $this>
     */
    public function meterPoint(): BelongsTo
    {
        return $this->belongsTo(MeterPoint::class);
    }

    /**
     *  registrations in a blocking state.
     *
     * @param  Builder<self>  $query
     */
    public function scopeBlocking(Builder $query): void
    {
        $query->whereIn('state', EnergyCommunityMeterPointState::blocking());
    }

    /**
     * periods overlapping [$from, $to], inclusive; null $to / to_date = open ended.
     * a.from <= b.to (or b.to null) AND b.from <= a.to (or a.to null).
     *
     * @param  Builder<self>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface|string $from, CarbonInterface|string|null $to): void
    {
        $from = $from instanceof CarbonInterface ? $from->toDateString() : $from;
        $to = $to instanceof CarbonInterface ? $to->toDateString() : $to;

        $query->where(fn (Builder $q) => $q->whereNull('to_date')->orWhere('to_date', '>=', $from));

        if ($to !== null) {
            $query->where('from_date', '<=', $to);
        }
    }

    /**
     * Registrations of communities the user belongs to; admins see all.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->is_admin) {
            return;
        }

        $query->whereHas('energyCommunity', fn (Builder $q) => $q->visibleTo($user));
    }
}
