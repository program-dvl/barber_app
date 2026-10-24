<?php

namespace App\Domain\HelpCenter;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HelpContext
{
    public function __construct(private readonly HelpCatalog $catalog) {}

    public function for(Request $request): array
    {
        $route = $request->route()?->getName() ?? '';
        $category = match (true) {
            Str::startsWith($route, ['business.team.', 'business.staff.', 'staff-invitations.']) => 'team',
            Str::startsWith($route, 'business.services.') => 'services',
            Str::startsWith($route, ['business.configuration.', 'business.locations.']) => 'business',
            Str::startsWith($route, ['business.calendar', 'business.dashboard', 'business.appointments.']) => 'calendar',
            Str::startsWith($route, 'business.walk-ins.') => 'walk-ins',
            Str::startsWith($route, 'business.clients.') => 'clients',
            Str::startsWith($route, ['business.checkout.', 'business.sales.']) => 'checkout',
            Str::startsWith($route, 'business.inventory.') => 'inventory',
            Str::startsWith($route, 'business.reports.') => 'reports',
            Str::startsWith($route, 'business.communications.') => 'notifications',
            Str::startsWith($route, 'business.billing.') => 'billing',
            Str::startsWith($route, ['profile.', 'business.activity.']) => 'account',
            default => 'getting-started',
        };

        return [
            'category' => collect($this->catalog->categories())->firstWhere('slug', $category),
            'articles' => array_slice($this->catalog->summaries($category), 0, 3),
        ];
    }
}
