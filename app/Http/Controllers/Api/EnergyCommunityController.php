<?php

namespace App\Http\Controllers\Api;

use App\Actions\ActivateEnergyCommunity;
use App\Actions\CreateEnergyCommunity;
use App\Actions\RejectEnergyCommunity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\EnergyCommunities\IndexEnergyCommunityRequest;
use App\Http\Requests\Api\EnergyCommunities\StoreEnergyCommunityRequest;
use App\Http\Resources\EnergyCommunityResource;
use App\Models\EnergyCommunity;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EnergyCommunityController extends Controller
{
    /**
     * GET /api/energy-communities
     * Scoped in the query (BR-11), paginated, filterable by state.
     */
    public function index(IndexEnergyCommunityRequest $request): AnonymousResourceCollection
    {
        $communities = EnergyCommunity::query()
            ->visibleTo($request->user())
            ->when(
                $request->validated('state'),
                fn ($query, string $state) => $query->where('state', $state),
            )
            ->orderBy('id')
            ->paginate();

        return EnergyCommunityResource::collection($communities);
    }

    /**
     * POST /api/energy-communities
     * Creator becomes manager
     */
    public function store(StoreEnergyCommunityRequest $request, CreateEnergyCommunity $createEnergyCommunity): JsonResponse
    {
        Gate::authorize('create', EnergyCommunity::class);

        try {
            $community = $createEnergyCommunity->handle($request->user(), $request->validated());
        } catch (UniqueConstraintViolationException) {
            // Two requests with the same ecid both passed validation; the unique index stopped the second.
            throw ValidationException::withMessages([
                'ecid' => [__('validation.unique', ['attribute' => 'ecid'])],
            ]);
        }

        return EnergyCommunityResource::make($community->load('memberships.user'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/energy-communities/{energyCommunity}
     * Members (either role) and admins only; others get 403 (see NOTES.md).
     */
    public function show(EnergyCommunity $energyCommunity): EnergyCommunityResource
    {
        Gate::authorize('view', $energyCommunity);

        return EnergyCommunityResource::make($energyCommunity->load('memberships.user'));
    }

    /**
     * POST /api/energy-communities/{energyCommunity}/activate (BR-12)
     */
    public function activate(EnergyCommunity $energyCommunity, ActivateEnergyCommunity $activate): EnergyCommunityResource
    {
        Gate::authorize('activate', $energyCommunity);

        return EnergyCommunityResource::make($activate->handle($energyCommunity));
    }

    /**
     * POST /api/energy-communities/{energyCommunity}/reject (BR-13)
     */
    public function reject(EnergyCommunity $energyCommunity, RejectEnergyCommunity $reject): EnergyCommunityResource
    {
        Gate::authorize('reject', $energyCommunity);

        return EnergyCommunityResource::make($reject->handle($energyCommunity));
    }
}
