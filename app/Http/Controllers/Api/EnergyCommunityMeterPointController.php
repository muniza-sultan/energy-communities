<?php

namespace App\Http\Controllers\Api;

use App\Actions\RegisterMeterPoint;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Registrations\IndexRegistrationRequest;
use App\Http\Requests\Api\Registrations\RegisterMeterPointRequest;
use App\Http\Resources\RegistrationResource;
use App\Models\EnergyCommunity;
use App\Models\MeterPoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Registrations of metering points in one energy community.
 */
class EnergyCommunityMeterPointController extends Controller
{   //P2 Core
    /**
     * POST /api/energy-communities/{energyCommunity}/meter-points
     * BR-5 to BR-8.
     */
    public function store(
        RegisterMeterPointRequest $request,
        EnergyCommunity $energyCommunity,
        RegisterMeterPoint $registerMeterPoint,
    ): JsonResponse {
        $registration = $registerMeterPoint->handle(
            $energyCommunity,
            MeterPoint::findOrFail($request->validated('meter_point_id')),
            $request->validatedDate('from_date'),
            $request->validatedDate('to_date'),
            $request->validatedDate('consent_date'),
        );

        return RegistrationResource::make($registration->load('meterPoint'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/energy-communities/{energyCommunity}/meter-points
     * Filterable by state; paginated.
     */
    public function index(IndexRegistrationRequest $request, EnergyCommunity $energyCommunity): AnonymousResourceCollection
    {
        $registrations = $energyCommunity->registrations()
            // The metering point may be soft-deleted by now; its registration history stays readable.
            ->with(['meterPoint' => fn ($query) => $query->withTrashed()])
            ->when(
                $request->validated('state'),
                fn ($query, string $state) => $query->where('state', $state),
            )
            ->orderBy('id')
            ->paginate();

        return RegistrationResource::collection($registrations);
    }

    
}
