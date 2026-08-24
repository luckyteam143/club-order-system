<?php

namespace App\Http\Responses\Auth;

use App\Filament\Pages\StockScanner;
use App\Filament\Resources\OrderResource;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as Responsable;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements Responsable
{
    /**
     * Employees log in on a handheld scanner device to do one thing —
     * scan stock in/out — so they skip the admin Dashboard entirely and
     * land straight on the Scan Stock page. Club users land straight on
     * their Orders list instead of the (otherwise empty-feeling) Dashboard.
     * Every other role keeps the normal post-login redirect.
     */
    public function toResponse($request): RedirectResponse | Redirector
    {
        $user = Filament::auth()->user();

        if ($user?->hasRole('employee')) {
            return redirect()->intended(StockScanner::getUrl());
        }

        if ($user?->isClub()) {
            return redirect()->intended(OrderResource::getUrl('index'));
        }

        return redirect()->intended(Filament::getUrl());
    }
}
