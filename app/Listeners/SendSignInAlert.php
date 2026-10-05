<?php

namespace App\Listeners;

use App\Domain\AccountNotifications\Services\SignInAlertService;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class SendSignInAlert
{
    public function __construct(
        private readonly SignInAlertService $alerts,
        private readonly Request $request,
    ) {}

    public function handle(Login $event): void
    {
        $this->alerts->record($event, $this->request);
    }
}
