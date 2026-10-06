<?php

namespace App\Http\Controllers;

use App\Support\Principal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\Passport;

/** Self-introspection of the presented token (RFC 7662-like, but only for the caller's own token). */
class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $principal = Principal::from($request);
        $token = Passport::token()->newQuery()->find($principal->tokenId);

        return response()->json([
            'active' => true,
            'subject_type' => $principal->type,
            'subject_id' => $principal->id,
            'client_id' => $principal->clientId,
            'scopes' => $principal->scopes,
            'expires_at' => $token?->expires_at?->toIso8601String(),
        ]);
    }
}
