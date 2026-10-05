import { zonedParts } from './calendarWorkspace.js';
export const waitingStatuses = new Set(['waiting', 'assigned', 'notified']);
export function elapsedMinutes(value, now) { return Math.max(0, Math.floor((Number(now) - Date.parse(value)) / 60000)); }
export function waitLabel(minutes) { return minutes >= 1440 ? `${Math.floor(minutes / 1440)}d ${Math.floor(minutes % 1440 / 60)}h` : minutes < 60 ? `${minutes} min` : `${Math.floor(minutes / 60)}h ${minutes % 60}m`; }
export function estimateLabel(value, now, updatedAt) {
    if (Number(now) - Date.parse(updatedAt) > 120000) return 'Refresh estimate';
    if (!value) return 'Check availability';
    const minutes = Math.max(0, Math.ceil((Date.parse(value) - Number(now)) / 60000));
    if (!minutes) return 'Next available';
    const upper = Math.ceil(minutes / 5) * 5;
    return upper <= 5 ? 'Within ~5 min' : `${Math.max(5, upper - 5)}–${upper} min`;
}
export function pastQuote(entry, now) { return !!entry.original_estimated_at && waitingStatuses.has(entry.status) && Date.parse(entry.original_estimated_at) < Number(now); }
export function matchesQueue(entry, query) {
    const term = query.trim().toLocaleLowerCase();
    if (!term) return true;
    const words = [entry.client_name, entry.client_mobile, entry.service_name, entry.preferred_staff_name, entry.assigned_staff_name].filter(Boolean).join(' ').toLocaleLowerCase();
    return words.includes(term) || (/^[+\d\s()-]+$/.test(term) && (entry.client_mobile || '').replace(/\D/g, '').includes(term.replace(/\D/g, '')));
}
export function localInput(value, zone) { const p = zonedParts(value, zone); return `${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}`; }
