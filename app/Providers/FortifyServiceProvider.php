<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', strtolower($request->string('email')))->where('active', true)->first();

            return $user && Hash::check($request->input('password', ''), $user->password) ? $user : null;
        });
        Fortify::loginView(fn () => view('auth.form', ['mode' => 'login']));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.form', ['mode' => 'forgot']));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.form', ['mode' => 'reset', 'request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.form', ['mode' => 'verify']));
        Fortify::confirmPasswordView(fn () => view('auth.form', ['mode' => 'confirm']));
        Fortify::twoFactorChallengeView(fn () => view('auth.form', ['mode' => 'two-factor']));
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower($request->input('email', '')).'|'.$request->ip()));
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));
    }
}
