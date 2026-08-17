<?php

namespace App\Support\Jobs;

interface EntitlementAwareJob
{
    public function businessId(): int;

    public function entitlementKey(): string;

    public function entitlementOperation(): string;

    public function entitlementIncrease(): int;
}
