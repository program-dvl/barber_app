export const clientInitials = name => (name || '').trim().split(/\s+/).slice(0,2).map(part=>Array.from(part)[0] || '').join('').toUpperCase();
// Historical preferences use either a selected-channel list or a boolean map.
// Transactional email stays enabled unless explicitly withdrawn; SMS is opt-in.
export const clientCommunicationChannels = preferences => ['email', 'sms'].filter(channel =>
    channel === 'email' ? preferences?.email !== false : Array.isArray(preferences) ? preferences.includes(channel) : preferences?.[channel] === true);
// SQL aggregate timestamps are UTC even when they have no explicit offset.
export const clientInstant = value => value ? new Date(/[zZ]|[+-]\d\d:\d\d$/.test(value) ? value : value.replace(' ', 'T')+'Z') : null;
export function clientDate(value, zone='UTC') {const date=clientInstant(value);return date && Number.isFinite(date.getTime()) ? new Intl.DateTimeFormat(undefined,{day:'numeric',month:'short',year:'numeric',timeZone:zone}).format(date) : '—';}
export function clientDay(value, zone='UTC') {const date=clientInstant(value);return date && Number.isFinite(date.getTime()) ? new Intl.DateTimeFormat(undefined,{weekday:'short',hour:'numeric',minute:'2-digit',timeZone:zone}).format(date) : '—';}
export const clientMoney=(minor,currency,locale)=>new Intl.NumberFormat(locale || undefined,{style:'currency',currency}).format(Number(minor || 0)/10**new Intl.NumberFormat('en',{style:'currency',currency}).resolvedOptions().maximumFractionDigits);
