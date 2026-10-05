export function durationLabel(value) {
    const minutes = Math.max(0, Number(value) || 0);
    const hours = Math.floor(minutes / 60);
    return [hours ? `${hours} hr` : '', minutes % 60 ? `${minutes % 60} min` : ''].filter(Boolean).join(' ') || '0 min';
}
export function filterServices(services, filters) {
    const search = (filters.search || '').trim().toLocaleLowerCase();
    return services.filter(s => {
        if (search && !`${s.name} ${s.category || ''}`.toLocaleLowerCase().includes(search)) return false;
        if (filters.category && (filters.category === 'uncategorised' ? s.category_id : s.category_id !== filters.category)) return false;
        if (filters.kind && s.kind !== filters.kind) return false;
        if (filters.status === 'active' && !s.is_active || filters.status === 'inactive' && s.is_active || filters.status === 'setup' && (!s.is_active || !s.warnings.length)) return false;
        if (filters.channel === 'online' && (!s.is_active || !s.online_visible) || filters.channel === 'internal' && s.online_visible) return false;
        if (filters.staff && !s.staff.some(p => p.public_id === filters.staff)) return false;
        if (filters.location && !s.locations.some(l => l.public_id === filters.location)) return false;
        return true;
    }).sort((a, b) => {
        switch (filters.sort) {
            case 'price': return a.price_minor - b.price_minor || a.name.localeCompare(b.name);
            case 'duration': return a.duration_minutes + a.processing_minutes + a.cleanup_minutes - b.duration_minutes - b.processing_minutes - b.cleanup_minutes || a.name.localeCompare(b.name);
            case 'updated': return (b.updated_at || '').localeCompare(a.updated_at || '');
            case 'category': return Number(b.is_active) - Number(a.is_active) || a.category_order - b.category_order || (a.category || '').localeCompare(b.category || '') || a.name.localeCompare(b.name);
            default: return a.name.localeCompare(b.name);
        }
    });
}
export function decimalToMinor(value) {
    const normalized = String(value ?? '').trim();
    if (!/^\d+(\.\d{0,2})?$/.test(normalized)) return null;
    const [whole, fraction = ''] = normalized.split('.');
    const minor = Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
    return Number.isSafeInteger(minor) ? minor : null;
}
export function channelLabel(service) {
    if (!service.is_active) return 'Inactive';
    if (service.warnings?.length) return 'Needs setup';
    return service.online_visible ? 'Online enabled' : 'Internal only';
}

// Calendar local inputs and projected effective bounds share the location zone.
export function serviceVariantAt(service, staff, localStart) {
    const at = localStart?.length === 16 ? `${localStart}:00` : localStart;
    if (!service || !at || service.effective_from && service.effective_from > at || service.effective_until && service.effective_until <= at) return null;
    return (service.staff_variants || []).filter(v => v.staff === staff && (!v.effective_from || v.effective_from <= at) && (!v.effective_until || v.effective_until > at))
        .sort((a,b) => (b.effective_from || '').localeCompare(a.effective_from || ''))[0] || null;
}
export function calendarServiceChoices(services, lines, localStart) {
    const at = localStart?.length === 16 ? `${localStart}:00` : localStart;
    return services.filter(s => (!at || (!s.effective_from || s.effective_from <= at) && (!s.effective_until || s.effective_until > at)) && (s.kind !== 'addon' || lines.some(line => services.find(parent => parent.public_id === line.service && parent.kind !== 'addon')?.addon_ids?.includes(s.public_id))));
}

export function effectiveCatalogPreview(service, variant, locationPrice = null) {
    const active = Number(variant?.duration_minutes ?? service.active_minutes ?? service.duration_minutes ?? 0);
    const processing = Number(variant?.processing_minutes ?? service.processing_minutes ?? 0);
    const cleanup = Number(variant?.cleanup_minutes ?? service.cleanup_minutes ?? 0);
    return {price_minor: variant?.price_minor ?? locationPrice ?? service.price_minor, visit_minutes: active + processing, bookable_minutes: active + processing + cleanup};
}
