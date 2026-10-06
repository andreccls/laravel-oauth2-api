<?php

namespace App\Http\Controllers;

use App\Support\Principal;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Passport\Passport;

/** Revokes the presented access token and the refresh token tied to it (RFC 7009 spirit). */
class RevokeTokenController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $id = Principal::from($request)->tokenId;

        Passport::token()->newQuery()->whereKey($id)->update(['revoked' => true]);
        Passport::refreshToken()->newQuery()->where('access_token_id', $id)->update(['revoked' => true]);

        return response()->noContent();
    }
}
