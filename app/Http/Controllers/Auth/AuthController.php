<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        /** @var User|null $user */
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        /*
         * Revoke only this device's previous token, not every token the user
         * has. The comment here used to say "for this device" while the code
         * deleted all of them - so on a CRM that ships as an installable PWA,
         * signing in on a phone silently signed the same person out on their
         * desktop, and vice versa.
         *
         * Naming the token per device keeps that from happening while still
         * stopping tokens accumulating without limit on repeated logins.
         */
        $deviceName = $this->deviceTokenName($request);

        $user->tokens()->where('name', $deviceName)->delete();

        return response()->json([
            'user' => $user->load('role'),
            'token' => $user->createToken($deviceName)->plainTextToken,
        ]);
    }

    /**
     * A stable, non-identifying name for the device making this request, so
     * each device keeps its own token.
     */
    private function deviceTokenName(Request $request): string
    {
        $agent = (string) $request->userAgent();

        return 'spa-token:'.substr(hash('sha256', $agent ?: 'unknown'), 0, 16);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('role'));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }
}


