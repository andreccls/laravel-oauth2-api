<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Session login for the resource owner. Needed ONLY by the authorization-code flow: /oauth/authorize
 * requires a logged-in user. (Headless: JSON in, cookie out, no HTML.)
 */
class LoginController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        if (! Auth::guard('web')->attempt($credentials)) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }
        $request->session()->regenerate();

        return response()->noContent();
    }
}
