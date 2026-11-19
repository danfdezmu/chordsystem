<?php

namespace App\Http\Controllers;

use App\Support\SharedAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SsoAcceptController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $token = (string) $request->query('token', '');
        $identity = SharedAuth::readToken($token);

        abort_unless($identity !== null && $identity['verified'], 403);
        abort_if(Cache::has($this->cacheKey($token)), 403);

        Cache::put($this->cacheKey($token), true, now()->addMinutes(2));

        $user = SharedAuth::provision($identity);
        Auth::login($user);
        $request->session()->put('shared_auth', true);
        $request->session()->put('auth.password_confirmed_at', now()->unix());

        return redirect()
            ->intended(route('dashboard'))
            ->withHeaders([
                'Referrer-Policy' => 'no-referrer',
            ]);
    }

    private function cacheKey(string $token): string
    {
        return 'shared-auth:'.hash('sha256', $token);
    }
}
