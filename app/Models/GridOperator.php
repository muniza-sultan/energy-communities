<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GridOperator extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'identifier'];

    /**
     * meter_points.grid_operator_id holds our `identifier`, not  `id`.
     *
     * @return HasMany<MeterPoint, $this>
     */
    public function meterPoints(): HasMany
    {
        return $this->hasMany(MeterPoint::class, 'grid_operator_id', 'identifier');
    }
}
