const finished = new Set(['completed', 'cancelled_by_client', 'cancelled_by_shop', 'no_show', 'rescheduled']);
export const isFinished = event => finished.has(event.status);
export function dayGroups(events, now, isToday) {
    const groups = { now: [], next: [], later: [], unresolved: [], finished: [] };
    for (const event of events) {
        if (isFinished(event)) groups.finished.push(event);
        else if (!isToday) groups.later.push(event);
        else if (['in_service', 'arrived', 'checked_in'].includes(event.status)) groups.now.push(event);
        else if (new Date(event.startsAt).getTime() < now) groups.unresolved.push(event);
        else if (new Date(event.startsAt).getTime() - now <= 120 * 60000) groups.next.push(event);
        else groups.later.push(event);
    }
    return groups;
}
export function attentionItems(events, now, isToday) {
    return events.filter(event => !isFinished(event)).flatMap(event => {
        const pastStart = isToday && new Date(event.startsAt).getTime() < now;
        const overrun = isToday && event.status === 'in_service' && new Date(event.endsAt).getTime() < now;
        const reasons = [];
        if (overrun) reasons.push('Past scheduled finish');
        if (pastStart && ['confirmed', 'pending_confirmation', 'late'].includes(event.status)) reasons.push('Arrival not recorded');
        else if (event.status === 'late') reasons.push('Marked late');
        if (event.unassigned) reasons.push('Staff not assigned');
        if (event.status === 'pending_confirmation') reasons.push('Needs confirmation');
        if (event.forms?.pending > 0) reasons.push(`${event.forms.pending} incomplete form${event.forms.pending > 1 ? 's' : ''}`);
        return reasons.length ? [{event, reasons, priority: overrun || pastStart ? 0 : event.unassigned ? 1 : event.status === 'pending_confirmation' ? 2 : 3}] : [];
    }).sort((a, b) => a.priority - b.priority || new Date(a.event.startsAt) - new Date(b.event.startsAt));
}
export const localDateKey = (value, timeZone) => {
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA', {timeZone, year:'numeric', month:'2-digit', day:'2-digit'}).formatToParts(new Date(value)).map(p => [p.type,p.value]));
    return `${parts.year}-${parts.month}-${parts.day}`;
};
