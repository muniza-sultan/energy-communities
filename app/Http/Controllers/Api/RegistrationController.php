<?php

namespace App\Http\Controllers\Api;

use App\Actions\TransitionRegistration;
use App\Exceptions\ConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Registrations\TransitionRegistrationRequest;
use App\Http\Resources\RegistrationResource;
use App\Models\EnergyCommunityMeterPoint;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * {registration} binds the EnergyCommunityMeterPoint model (implicit binding by parameter name).
 */
class RegistrationController extends Controller
{
    /**
     * POST /api/registrations/{registration}/transition (BR-9)
     */
    public function transition(
        TransitionRegistrationRequest $request,
        EnergyCommunityMeterPoint $registration,
        TransitionRegistration $transition,
    ): RegistrationResource {
        $registration = $transition->handle(
            $registration,
            $request->targetState(),
            $request->validated('status_code'),
        );

        return RegistrationResource::make($registration->load('meterPoint'));
    }

    /**
     * DELETE /api/registrations/{registration} (BR-10)
     * Never a hard delete: applies the ending transition for the current state.
     * 204 (generic HTTP semantics for DELETE, see NOTES.md).
     */
    public function destroy(EnergyCommunityMeterPoint $registration, TransitionRegistration $transition): Response
    {
        Gate::authorize('delete', $registration);

        $transition->end($registration)
            ?? throw new ConflictException('This registration has already ended.');

        return response()->noContent();
    }
}
