<?php

namespace App\Filament\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;

class AdminLogin extends Login
{
    public function mount(): void
    {
        if (Filament::auth()->user()?->canAccessPanel(Filament::getPanel('admin'))) {
            session()->forget('url.intended');
            redirect()->to(Filament::getUrl());

            return;
        }

        // A salon session sharing the web guard must still be able to see admin sign-in.
        $this->form->fill();
    }

    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        if ($response !== null) {
            session()->forget([
                'url.intended',
                'platform_session_started_at',
                'platform_session_last_used_at',
                'support_access_session_id',
            ]);
        }

        return $response;
    }
}
