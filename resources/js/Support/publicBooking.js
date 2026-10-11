// Date-only arithmetic deliberately avoids UTC conversion of location wall dates.
export const shiftDate = (value, days) => {
    const date = new Date(`${value}T12:00:00Z`);
    date.setUTCDate(date.getUTCDate() + days);
    return date.toISOString().slice(0, 10);
};
export const slotGroups = (slots, zone) => {
    const groups = [{ label: 'Morning', slots: [] }, { label: 'Afternoon', slots: [] }, { label: 'Evening', slots: [] }];
    const formatter = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', hourCycle: 'h23', timeZone: zone });
    for (const slot of slots) {
        const hour = Number(formatter.format(new Date(slot.starts_at_utc)));
        groups[hour < 12 ? 0 : hour < 17 ? 1 : 2].slots.push(slot);
    }
    return groups.filter(group => group.slots.length);
};
export const eligibleService = (service, kind, policy) => (!service.client_eligibility || service.client_eligibility === 'all' || service.client_eligibility === kind)
    && !(kind === 'new' && policy.online_new_client_rule === 'existing_only')
    && !(kind === 'new' && policy.online_new_client_rule === 'consultation_only' && !service.consultation_required);
export const safeRead = (key, storage) => {
    try { return JSON.parse(storage.getItem(key) || 'null'); } catch { return null; }
};
export const safeWrite = (key, value, storage) => {
    try { value === null ? storage.removeItem(key) : storage.setItem(key, JSON.stringify(value)); } catch { /* Booking works when browser storage is disabled. */ }
};
