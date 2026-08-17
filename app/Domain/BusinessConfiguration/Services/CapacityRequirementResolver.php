<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\BusinessConfiguration\Data\CapacityRequirement;
use App\Domain\BusinessConfiguration\Models\PhysicalResource;
use App\Domain\BusinessConfiguration\Models\Service;

class CapacityRequirementResolver
{
    /** @param list<Service> $addons @return list<CapacityRequirement> */
    public function resolve(Service $service, array $addons = []): array
    {
        $services = [$service, ...$addons];
        $requirements = [];
        foreach ($services as $line) {
            if ($line->business_id !== $service->business_id) {
                throw new \InvalidArgumentException('Add-ons must belong to the same business as the service.');
            }
            foreach ($line->resourceRequirements()->get() as $requirement) {
                $resource = PhysicalResource::query()->forBusiness($service->business_id)->findOrFail($requirement->physical_resource_id);
                $key = $resource->id.':'.($requirement->service_segment_id ?? 'line');
                $requirements[$key] ??= ['resource' => $resource, 'segment_id' => $requirement->service_segment_id, 'quantity' => 0];
                $requirements[$key]['quantity'] += $requirement->quantity;
            }
        }

        return array_values(array_map(fn (array $item) => new CapacityRequirement(
            $item['resource']->id, $item['resource']->public_id, $item['resource']->name,
            $item['segment_id'], $item['quantity'], $item['resource']->is_active ? $item['resource']->quantity : 0,
            $item['resource']->is_active && $item['quantity'] <= $item['resource']->quantity,
        ), $requirements));
    }
}
