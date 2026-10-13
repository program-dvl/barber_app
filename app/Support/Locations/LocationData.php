<?php

namespace App\Support\Locations;

use App\Domain\PlatformAccess\Models\Location;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Support\Regional\CountryCatalog;
use Illuminate\Validation\Rule;

final class LocationData
{
    public const FIELDS = ['google_place_id', 'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country_code', 'entrance_latitude', 'entrance_longitude', 'entrance_instructions', 'parking_instructions', 'is_primary'];

    public static function rules(): array
    {
        return [
            'google_place_id' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:150'],
            'state' => ['nullable', 'string', 'max:150'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'country_code' => ['nullable', 'string', Rule::in(array_keys(app(CountryCatalog::class)->countries()))],
            'entrance_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:entrance_longitude'],
            'entrance_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:entrance_latitude'],
            'entrance_instructions' => ['nullable', 'string', 'max:1000'],
            'parking_instructions' => ['nullable', 'string', 'max:1000'],
            'is_primary' => ['sometimes', 'boolean'],
            'location_revision' => ['sometimes', 'nullable', 'string', 'size:64'],
        ];
    }

    public static function revision(Location $location): string
    {
        $data = $location->only(['name', 'address', 'time_zone', 'phone', 'email', ...self::FIELDS]);
        $data['is_primary'] = (bool) $data['is_primary'];

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    public static function snapshot(Location $location): array
    {
        // Provider geometry is deliberately excluded from durable appointment history.
        return $location->only(['public_id', 'name', 'address', 'time_zone', ...self::FIELDS]);
    }

    public static function forAppointment(Appointment $appointment): array
    {
        return self::present($appointment->location_snapshot ?: self::snapshot($appointment->location));
    }

    public static function present(array $data): array
    {
        $hasEntrance = isset($data['entrance_latitude'], $data['entrance_longitude']);
        $destination = $hasEntrance
            ? $data['entrance_latitude'].','.$data['entrance_longitude']
            : (($data['address'] ?? '') ?: (! empty($data['google_place_id']) ? ($data['name'] ?? 'Selected location') : ''));
        $parameters = ['api' => 1, 'destination' => $destination];
        if (! $hasEntrance && ! empty($data['google_place_id'])) {
            $parameters['destination_place_id'] = $data['google_place_id'];
        }

        return [...$data,
            'directions_url' => filled($destination) ? 'https://www.google.com/maps/dir/?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986) : null,
            'apple_maps_url' => $hasEntrance ? 'https://maps.apple.com/?'.http_build_query(['daddr' => $destination], '', '&', PHP_QUERY_RFC3986) : null,
        ];
    }
}
