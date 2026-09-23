<?php

namespace App\Http\Controllers\Api;

use App\Actions\AddUserToEnergyCommunity;
use App\Enums\EnergyCommunityUserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\EnergyCommunities\AddEnergyCommunityUserRequest;
use App\Http\Resources\EnergyCommunityMemberResource;
use App\Models\EnergyCommunity;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class EnergyCommunityUserController extends Controller
{
    /**
     * POST /api/energy-communities/{energyCommunity}/users
     * Body: { "user_id": 7, "role": "member" } (BR-4)
     */
    public function store(
        AddEnergyCommunityUserRequest $request,
        EnergyCommunity $energyCommunity,
        AddUserToEnergyCommunity $addUser,
    ): JsonResponse {
        $membership = $addUser->handle(
            $energyCommunity,
            User::findOrFail($request->validated('user_id')),
            EnergyCommunityUserRole::from($request->validated('role')),
        );

        return EnergyCommunityMemberResource::make($membership->load('user'))
            ->response()
            ->setStatusCode(201);
    }
}
