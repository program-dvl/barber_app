export const locationFields = ['google_place_id', 'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country_code', 'entrance_latitude', 'entrance_longitude', 'entrance_instructions', 'parking_instructions', 'is_primary'];

export function locationDefaults(location = {}) {
    return Object.fromEntries(locationFields.map(key => [key, location[key] ?? (key === 'is_primary' ? false : '')]));
}

export function placeAddress(place) {
    const component = (type, short = false) => {
        const value = place.addressComponents?.find(item => item.types.includes(type));
        return (short ? value?.shortText : value?.longText) || '';
    };
    return {
        google_place_id: place.id || '',
        address: place.formattedAddress || '',
        address_line_1: [component('street_number'), component('route')].filter(Boolean).join(' '),
        ...(component('subpremise') ? { address_line_2: component('subpremise') } : {}),
        city: component('locality') || component('postal_town') || component('sublocality_level_1') || component('administrative_area_level_2'),
        state: component('administrative_area_level_1'),
        postal_code: [component('postal_code'), component('postal_code_suffix')].filter(Boolean).join('-'),
        country_code: component('country', true),
        // Provider geometry stays in the component's temporary memory.
        entrance_latitude: '', entrance_longitude: '',
    };
}

export function coordinates(location) {
    const lat = location?.entrance_latitude, lng = location?.entrance_longitude;
    if (lat === '' || lng === '' || lat == null || lng == null) return null;
    const point = { lat: Number(lat), lng: Number(lng) };
    return Number.isFinite(point.lat) && Number.isFinite(point.lng) && Math.abs(point.lat) <= 90 && Math.abs(point.lng) <= 180 ? point : null;
}

export function distanceKm(a, b) {
    const rad = value => value * Math.PI / 180;
    const h = Math.sin(rad(b.lat - a.lat) / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(rad(b.lng - a.lng) / 2) ** 2;
    return 6371 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(Math.max(0, 1 - h)));
}

export function discoverBranches(branches, query = '', origin = null) {
    const text = query.trim().toLocaleLowerCase();
    return branches.filter(item => `${item.name} ${item.address || ''} ${item.city || ''} ${item.postal_code || ''}`.toLocaleLowerCase().includes(text))
        .map(item => ({ ...item, distance: origin && coordinates(item) ? distanceKm(origin, coordinates(item)) : null }))
        .sort((a, b) => origin ? (a.distance ?? Infinity) - (b.distance ?? Infinity) : 0);
}

export function navigationLinks(location) {
    const point = coordinates(location);
    const destination = point ? `${point.lat},${point.lng}` : location.address || (location.google_place_id ? location.name || 'Selected location' : '');
    const parameters = new URLSearchParams({ api: '1', destination });
    if (!point && location.google_place_id) parameters.set('destination_place_id', location.google_place_id);
    return {
        directions_url: destination ? `https://www.google.com/maps/dir/?${parameters}` : null,
        apple_maps_url: point ? `https://maps.apple.com/?${new URLSearchParams({ daddr: destination })}` : null,
    };
}

// Keep customer choices that remain available at the new branch; add-ons need a retained parent.
export function retainBranchServices(selectedIds, availableServices) {
    const eligible = selectedIds.filter(id => availableServices.some(item => item.public_id === id));
    const parents = availableServices.filter(item => eligible.includes(item.public_id) && item.kind !== 'addon');
    return eligible.filter(id => {
        const service = availableServices.find(item => item.public_id === id);
        return service.kind !== 'addon' || parents.some(parent => parent.addon_ids?.includes(id));
    });
}
