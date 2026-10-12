<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Google has one callback URL, so both flows come back through callback():
 * a guest signing in, or a signed-in staff member connecting their Google
 * account (marked by the session key link() sets).
 */
class GoogleAuthController extends Controller
{
    protected const LINK_INTENT = 'google.link';

    public function redirect(): SymfonyRedirect
    {
        abort_unless(config('services.google.client_id'), 404);

        return Socialite::driver('google')->redirect();
    }

    public function link(): SymfonyRedirect
    {
        abort_unless(config('services.google.client_id'), 404);

        session()->put(self::LINK_INTENT, true);

        // Always show Google's account chooser, so a different account can be picked.
        return Socialite::driver('google')->with(['prompt' => 'select_account'])->redirect();
    }

    public function callback(AccountService $accounts): RedirectResponse
    {
        abort_unless(config('services.google.client_id'), 404);

        if (Auth::check()) {
            return $this->finishLink($accounts);
        }

        try {
            $user = $accounts->signInWithGoogle(Socialite::driver('google')->user());
        } catch (\RuntimeException $e) {
            return redirect()->route('login')->withErrors(['email' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')->withErrors(['email' => 'Google sign-in did not finish. Try again.']);
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended($user->homeRoute());
    }

    protected function finishLink(AccountService $accounts): RedirectResponse
    {
        $user = Auth::user();

        if (! session()->pull(self::LINK_INTENT) || $user->isStudent()) {
            return redirect($user->homeRoute());
        }

        // Back where they started: the setup page (or, once setup is done, the dashboard), or the profile.
        $settingUp = $user->needsAccountSetup();
        $back = fn () => redirect()->route(match (true) {
            $user->fresh()->needsAccountSetup() => 'staff.account.setup',
            $settingUp => 'dashboard',
            default => 'staff.profile',
        });

        try {
            $accounts->linkGoogle($user, Socialite::driver('google')->user());
        } catch (\RuntimeException $e) {
            return $back()->with('notice', ['title' => 'Google account not connected', 'message' => $e->getMessage(), 'tone' => 'error']);
        } catch (Throwable $e) {
            report($e);

            return $back()->with('notice', ['title' => 'Google account not connected', 'message' => 'Google did not finish connecting. Try again.', 'tone' => 'error']);
        }

        return $back()->with('notice', ['title' => 'Google account connected', 'message' => 'You can now log in with Google.']);
    }
}
