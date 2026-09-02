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
         * Switching somebody off has to actually switch them off.
         *
         * `is_active` was checked in exactly one place - the gate to the
         * Filament panel - so marking a leaver inactive removed the back office
         * and nothing else. They kept full access to the SPA and the whole API,
         * and any token they already held stayed valid, until the row was
         * deleted. Two accounts on this system are in that state today.
         *
         * The wording is deliberately not "your account is disabled": that
         * confirms the address exists to anybody guessing.
         */
        if (! $user->is_active) {
            $user->tokens()->delete();

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

        $user->load('role');

        return response()->json([
            'user' => $user->toArray() + ['nav_sections' => $user->navSectionMap()],
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
        $user = $request->user()->load('role');

        // The resolved answer for every section, worked out in one place.
        //
        // The SPA used to re-implement the whole precedence chain in its auth
        // store, and the two had already drifted - POS Support was special-cased
        // on the client only, and neither side knew about per-user grants. A
        // permission system with two implementations has two answers.
        return response()->json(
            $user->toArray() + ['nav_sections' => $user->navSectionMap()]
        );
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }
}


