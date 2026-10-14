<?php

namespace App\Http\Controllers\Api;

use App\Actions\RegisterOrganisation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterOrganisation $registerOrganisation): JsonResponse
    {
        $user = $registerOrganisation->handle($request->organisationData(), $request->ownerData());

        return $this->respondWithToken($user, $request->string('device_name')->value(), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->value())->first();

        // Hash something even when the email is unknown, so the response time
        // doesn't reveal which addresses are registered.
        $passwordMatches = Hash::check(
            $request->string('password')->value(),
            $user->password ?? Hash::make(Str::random(32)),
        );

        if ($user === null || ! $passwordMatches) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        return $this->respondWithToken($user, $request->string('device_name')->value(), 200);
    }

    public function logout(#[CurrentUser] User $user): Response
    {
        // Revokes only the token used for this request; other devices stay signed in.
        $user->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(#[CurrentUser] User $user): UserResource
    {
        return new UserResource($user->load('organisation'));
    }

    private function respondWithToken(User $user, string $deviceName, int $status): JsonResponse
    {
        $token = $user->createToken($deviceName)->plainTextToken;

        return (new UserResource($user->load('organisation')))
            ->additional(['token' => $token])
            ->response()
            ->setStatusCode($status);
    }
}
