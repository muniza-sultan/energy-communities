<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MeterPoints\IndexMeterPointRequest;
use App\Http\Requests\Api\MeterPoints\StoreMeterPointRequest;
use App\Http\Resources\MeterPointResource;
use App\Models\MeterPoint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MeterPointController extends Controller
{
    /**
     * GET /api/meter-points
     * Own metering points; admins see all (BR-2, enforced in the query).
     */
    public function index(IndexMeterPointRequest $request): AnonymousResourceCollection
    {
        $meterPoints = MeterPoint::query()
            ->visibleTo($request->user())
            ->when(
                $request->validated('energy_direction'),
                fn ($query, string $direction) => $query->where('energy_direction', $direction),
            )
            ->orderBy('id')
            ->paginate(); // laravel's default

        return MeterPointResource::collection($meterPoints);
    }

    /**
     * POST /api/meter-points
     * The caller registers a metering point of their own (BR-1, BR-2).
     */
    public function store(StoreMeterPointRequest $request): JsonResponse
    {
        Gate::authorize('create', MeterPoint::class);

        $name = $request->validated('name');

        try {
            $meterPoint = MeterPoint::create([
                'name' => $name,
                'energy_direction' => $request->validated('energy_direction'),
                'grid_operator_id' => MeterPoint::gridOperatorIdentifierFrom($name),
            
                'user_id' => $request->user()->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two requests with the same code both passed validation; the unique index stopped the second.
            throw ValidationException::withMessages([
                'name' => [__('validation.unique', ['attribute' => 'name'])],
            ]);
        }

        return MeterPointResource::make($meterPoint)
            ->response()
            ->setStatusCode(201);
    }
}
