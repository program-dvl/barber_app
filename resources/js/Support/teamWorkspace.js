import { dateKey, subtractBusy, dayInterval } from './calendarWorkspace.js';
export const dayNames = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
export const isTimeOff = r => ['leave','sick_leave','holiday'].includes(r.kind);
export const ruleLabel = r => ({working:'Working hours',temporary_change:'Temporary hours',break:'Break',personal_block:'Unavailable',leave:'Time off',sick_leave:'Sick leave',holiday:'Holiday'}[r.kind] || 'Unavailable');
export function matchesStaff(person, query) { return [person.display_name,person.title,person.membership?.role_label,...person.locations.map(l=>l.name),...person.services.map(s=>s.name)].filter(Boolean).join(' ').toLocaleLowerCase().includes(query.trim().toLocaleLowerCase()); }
export function mergeRanges(ranges) {
    const result=[];
    for(const r of ranges.map(r=>[Date.parse(r.startsAt),Date.parse(r.endsAt)]).filter(([a,b])=>a<b).sort((a,b)=>a[0]-b[0])) {
        const last=result.at(-1); if(last && r[0]<=last[1]) last[1]=Math.max(last[1],r[1]); else result.push([...r]);
    }
    return result.map(([a,b])=>({startsAt:new Date(a).toISOString(),endsAt:new Date(b).toISOString()}));
}
export const minutes = ranges => Math.round(mergeRanges(ranges).reduce((sum,r)=>sum+(Date.parse(r.endsAt)-Date.parse(r.startsAt))/60000,0));
export function staffDay(person, day, now, zone, today) {
    const context=day?.staff?.find(s=>s.id===person.public_id) || {windows:[],busy:[],unavailable:[]};
    const windows=mergeRanges(context.windows); const busy=context.busy;
    const gaps=subtractBusy(windows,busy);
    const availableMinutes=minutes(gaps), scheduledMinutes=minutes(windows), bookedMinutes=Math.max(0,scheduledMinutes-availableMinutes);
    const currentDay=day?.date===today;
    const contains=r=>Date.parse(r.startsAt)<=now && Date.parse(r.endsAt)>now;
    const exclusion=context.unavailable.find(contains);
    const current=busy.find(contains);
    const gap=gaps.find(contains);
    const next=busy.filter(b=>b.kind==='appointment' && Date.parse(b.startsAt)>now).sort((a,b)=>Date.parse(a.startsAt)-Date.parse(b.startsAt))[0];
    let state='off', label='Off today',until=null;
    if(person.status!=='active') { state='inactive';label='Inactive'; }
    else if(!currentDay) {state=windows.length?'scheduled':'off';label=windows.length?'Working':'Not scheduled';}
    else if(person.running_over) {state='overrun';label='Service running over';}
    else if(exclusion) {state=['leave','sick_leave','holiday'].includes(exclusion.kind)?'leave':exclusion.kind==='break'?'break':'blocked';label=state==='leave'?'On leave':state==='break'?'On break':'Unavailable';until=exclusion.endsAt;}
    else if(current) {state=['staff_break','break'].includes(current.kind)?'break':'busy';label=state==='break'?'On break':current.kind==='hold'?'Booking held':current.label==='Working at another location'?'At another branch':'Busy';until=current.endsAt;}
    else if(gap) {state=next && Date.parse(next.startsAt)-now<=30*60000?'soon':'available';label=state==='soon'?'Appointment soon':'Available now';until=gap.endsAt;}
    else if(windows.length && Date.parse(windows.at(-1).endsAt)<=now) {state='finished';label='Finished shift';}
    else if(windows.length && Date.parse(windows[0].startsAt)>now) {state='later';label='Starts later';until=windows[0].startsAt;}
    const outside=busy.filter(b=>b.kind==='appointment' && minutes(subtractBusy([b],windows))>0);
    const overlaps=busy.some((a,i)=>a.kind==='appointment' && busy.slice(i+1).some(b=>b.kind==='appointment' && a.appointmentId!==b.appointmentId && Date.parse(a.startsAt)<Date.parse(b.endsAt) && Date.parse(b.startsAt)<Date.parse(a.endsAt)));
    return {context,windows,busy,gaps,state,label,until,next,scheduledMinutes,bookedMinutes,availableMinutes,utilization:scheduledMinutes?Math.round(bookedMinutes/scheduledMinutes*100):0,working:windows.length>0,available:currentDay && !!gap && person.status==='active' && !person.running_over,gapMinutes:gap?Math.max(0,Math.floor((Date.parse(gap.endsAt)-now)/60000)):0,conflicts:new Set(outside.map(b=>b.appointmentId || b.startsAt)).size+(overlaps?1:0)};
}
export function shiftRules(person,date,locationId) {
    const day=new Date(`${date}T12:00:00Z`).getUTCDay() || 7;
    const rules=person.availability.filter(r=>!r.location_id || r.location_id===locationId);
    const dated=rules.filter(r=>r.starts_on && r.starts_on<=date && (r.ends_on || r.starts_on)>=date);
    const temporary=dated.filter(r=>r.kind==='temporary_change');
    return (temporary.length?temporary:rules.filter(r=>r.kind==='working' && r.day_of_week===day)).sort((a,b)=>a.starts_at.localeCompare(b.starts_at));
}
export function timelineStyle(range,date,zone) {
    const r=dayInterval(range,date,zone); if(!r) return null;
    return {left:`${r.start/14.4}%`,width:`${(r.end-r.start)/14.4}%`};
}
export function coverage(day, staffIds) {
    const team=day.staff.filter(s=>staffIds.includes(s.id));
    const windows=mergeRanges(day.windows);
    const points=[...new Set([...windows,...team.flatMap(s=>s.windows)].flatMap(r=>[Date.parse(r.startsAt),Date.parse(r.endsAt)]))].sort((a,b)=>a-b);
    const slices=[];
    for(let i=0;i<points.length-1;i++) {
        const from=points[i],until=points[i+1];
        if(!windows.some(w=>Date.parse(w.startsAt)<=from && Date.parse(w.endsAt)>=until)) continue;
        const count=team.filter(s=>s.windows.some(w=>Date.parse(w.startsAt)<=from && Date.parse(w.endsAt)>=until)).length;
        const last=slices.at(-1);
        if(last && last.count===count && Date.parse(last.endsAt)===from) last.endsAt=new Date(until).toISOString();
        else slices.push({startsAt:new Date(from).toISOString(),endsAt:new Date(until).toISOString(),count});
    }
    return slices;
}
export const durationLabel = n => n<60?`${n}m`:`${Math.floor(n/60)}h${n%60?` ${n%60}m`:''}`;
export const initialName = name => name.trim().split(/\s+/).slice(0,2).map(s=>s[0]).join('').toUpperCase();
export const localDate = (now,zone) => dateKey(now,zone);

// Arrow keys move within a tab group; Tab leaves the group through its active tab.
export function tabKey(event, keys, current, select, prefix) {
    if (!['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
    event.preventDefault();
    const index=keys.indexOf(current);
    const target=event.key==='Home'?keys[0]:event.key==='End'?keys.at(-1):keys[(index+(event.key==='ArrowRight'?1:-1)+keys.length)%keys.length];
    select(target);
    document.getElementById(`${prefix}${target}`)?.focus();
}
