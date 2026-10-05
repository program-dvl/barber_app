// Location wall-clock display; capacity arithmetic always uses real instants.
export const terminalStatuses = new Set(['completed', 'cancelled_by_client', 'cancelled_by_shop', 'no_show', 'rescheduled']);
export const canMove = event => !!event?.canManage && !terminalStatuses.has(event.status) && event.status !== 'in_service';
export function zonedParts(value, timeZone) {
    return Object.fromEntries(new Intl.DateTimeFormat('en-CA', {timeZone, year:'numeric', month:'2-digit', day:'2-digit', hour:'2-digit', minute:'2-digit', hourCycle:'h23'}).formatToParts(new Date(value)).filter(p => p.type !== 'literal').map(p => [p.type,p.value]));
}
export function dateKey(value, timeZone) {
    const p = zonedParts(value, timeZone);
    return `${p.year}-${p.month}-${p.day}`;
}
export function wallMinute(value, timeZone) {
    const p = zonedParts(value,timeZone);
    return Number(p.hour)*60 + Number(p.minute);
}
export function dayInterval(event, date, timeZone) {
    const first = dateKey(event.startsAt,timeZone), last = dateKey(event.endsAt,timeZone);
    if (first > date || last < date || (last === date && wallMinute(event.endsAt,timeZone) === 0)) return null;
    const start = first < date ? 0 : wallMinute(event.startsAt,timeZone);
    const end = last > date ? 1440 : wallMinute(event.endsAt,timeZone);
    // The repeated autumn hour cannot be represented unambiguously on a wall-time grid.
    return {start, end: Math.max(start + 5, end)};
}
export function subtractBusy(windows, busy, excludedId = null) {
    let ranges = windows.map(w => [Date.parse(w.startsAt),Date.parse(w.endsAt)]);
    for (const item of busy) {
        if (excludedId && item.appointmentId === excludedId) continue;
        const start = Date.parse(item.startsAt), end = Date.parse(item.endsAt);
        ranges = ranges.flatMap(([from,until]) => end <= from || start >= until ? [[from,until]] : [[from,Math.min(start,until)],[Math.max(end,from),until]].filter(([a,b]) => a < b));
    }
    return ranges.sort((a,b) => a[0]-b[0]).map(([from,until]) => ({startsAt:new Date(from).toISOString(),endsAt:new Date(until).toISOString()}));
}
export function layoutEntries(entries, date, zone, startMinute, endMinute, hourHeight = 64) {
    const sorted = entries.map(e => ({...e, interval:dayInterval(e,date,zone)})).filter(e => e.interval && e.interval.end > startMinute && e.interval.start < endMinute).sort((a,b) => a.interval.start-b.interval.start || a.interval.end-b.interval.end);
    let group=[], until=-1;
    const groups=[];
    for (const item of sorted) {
        if (group.length && item.interval.start >= until) {groups.push(group);group=[];until=-1;}
        group.push(item); until=Math.max(until,item.interval.end);
    }
    if(group.length) groups.push(group);
    return groups.flatMap(items => {
        const ends=[];
        const assigned=items.map(item => {
            let lane=ends.findIndex(end => end <= item.interval.start);
            if(lane < 0) lane=ends.length;
            ends[lane]=item.interval.end;
            return {...item,lane};
        });
        return assigned.map(item => {
            const from=Math.max(startMinute,item.interval.start), end=Math.min(endMinute,item.interval.end);
            const height=Math.max(12,(end-from)*hourHeight/60-2);
            return {...item,height,style:{top:`${(from-startMinute)*hourHeight/60}px`,height:`${height}px`,left:`calc(${item.lane*100/ends.length}% + 4px)`,width:`calc(${100/ends.length}% - 8px)`}};
        });
    });
}
export function staffEntries(events, staffId) {
    return events.flatMap(event => {
        if (staffId === 'unassigned') return event.unassigned ? [{...event,renderId:event.id}] : [];
        if (!event.staff.some(s => s.id === staffId)) return [];
        const segments=(event.segments || []).filter(s => s.staffId === staffId).sort((a,b) => Date.parse(a.startsAt)-Date.parse(b.startsAt));
        if(!segments.length) return [{...event,renderId:event.id}];
        const ranges=[];
        for (const segment of segments) {
            const last=ranges.at(-1);
            if(last && last.endsAt === segment.startsAt && last.processing === !segment.occupiesStaff) last.endsAt=segment.endsAt;
            else ranges.push({startsAt:segment.startsAt,endsAt:segment.endsAt,processing:!segment.occupiesStaff});
        }
        return ranges.map((r,i) => ({...event,originalStartsAt:event.startsAt,originalEndsAt:event.endsAt,...r,renderId:`${event.id}-${i}`}));
    });
}
export function matchesSearch(event, search) {
    const query=search.trim().toLocaleLowerCase();
    if(!query) return true;
    const text=[event.title,event.clientMobile,event.reference,...event.services.map(s=>s.name),...event.staff.map(s=>s.name)].filter(Boolean).join(' ').toLocaleLowerCase();
    return text.includes(query) || (/^[+\d\s()-]+$/.test(query) && (event.clientMobile || '').replace(/\D/g,'').includes(query.replace(/\D/g,'')));
}
export function clockInput(date, minute) {
    return `${date}T${String(Math.floor(minute/60)).padStart(2,'0')}:${String(minute%60).padStart(2,'0')}`;
}
