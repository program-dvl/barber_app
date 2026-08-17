<?php

namespace App\Domain\PlatformAccess\Actions;

use App\Domain\PlatformAccess\Models\StaffInvitation;

final readonly class IssuedStaffInvitation
{
    public function __construct(
        public StaffInvitation $invitation,
        public string $plainTextToken,
    ) {}
}
