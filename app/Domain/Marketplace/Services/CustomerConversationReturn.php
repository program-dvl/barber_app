<?php

namespace App\Domain\Marketplace\Services;

use Illuminate\Http\Request;

final class CustomerConversationReturn
{
    public function destination(Request $request): string
    {
        $saved = $request->session()->pull('marketplace_conversation_return');
        $path = is_array($saved) ? ($saved['path'] ?? '') : '';

        return is_string($path) && preg_match('#^/marketplace/account/bookings/[0-9A-HJKMNP-TV-Z]{26}/messages$#D', $path)
            && (int) ($saved['expires_at'] ?? 0) > now()->timestamp ? $path : '/marketplace/account';
    }
}
