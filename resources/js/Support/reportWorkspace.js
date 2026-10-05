export function reportMoney(value, currency = 'INR', locale) {
    if (value === null || value === undefined) return '—';
    const digits=new Intl.NumberFormat('en',{style:'currency',currency}).resolvedOptions().maximumFractionDigits;
    return new Intl.NumberFormat(locale, {style:'currency',currency,minimumFractionDigits:0,maximumFractionDigits:digits}).format(Number(value)/10**digits);
}
export function reportDuration(value) {
    if (value === null || value === undefined) return '—';
    const minutes = Math.round(Number(value));
    if (minutes < 60) return `${minutes} min`;
    return `${Math.floor(minutes/60)} hr${minutes%60 ? ` ${minutes%60} min` : ''}`;
}
export function reportChange(current, previous, format = value => String(value)) {
    if (current === null || current === undefined || previous === null || previous === undefined) return {text:'No comparison value',absolute:'—',percent:null};
    const difference = Number(current)-Number(previous);
    const absolute = `${difference > 0 ? '+' : difference < 0 ? '−' : ''}${format(Math.abs(difference))}`;
    if (difference === 0) return {text:'Unchanged',absolute,percent:0};
    if (Number(previous) <= 0) return {text:Number(previous)===0 ? 'No previous value' : 'Previous value is negative',absolute,percent:null};
    const percent = difference / Math.abs(Number(previous))*100;
    return {text:`${new Intl.NumberFormat(undefined,{maximumFractionDigits:1}).format(Math.abs(percent))}% ${difference>0 ? 'higher' : 'lower'}`,absolute,percent};
}
const parseDay = value => new Date(`${value}T12:00:00Z`);
const dateString = date => date.toISOString().slice(0,10);
const shiftDay = (value, days) => {const date=parseDay(value);date.setUTCDate(date.getUTCDate()+days);return dateString(date);};
export function reportToday(timeZone, now = new Date()) {
    const parts = new Intl.DateTimeFormat('en-GB',{timeZone,year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(now);
    const part = kind => parts.find(item=>item.type===kind)?.value;
    return `${part('year')}-${part('month')}-${part('day')}`;
}
export function reportPreset(key, today) {
    const date=parseDay(today); const day=date.getUTCDay(); const monday=shiftDay(today,-((day+6)%7));
    const first=`${today.slice(0,7)}-01`;
    switch (key) {
        case 'today': return [today,today];
        case 'yesterday': return [shiftDay(today,-1),shiftDay(today,-1)];
        case 'week': return [monday,today];
        case 'last_week': return [shiftDay(monday,-7),shiftDay(monday,-1)];
        case 'month': return [first,today];
        case 'last_month': {const end=shiftDay(first,-1);return [`${end.slice(0,7)}-01`,end];}
        case '30': return [shiftDay(today,-29),today];
        case '90': return [shiftDay(today,-89),today];
        case 'year': return [`${today.slice(0,4)}-01-01`,today];
        default: return null;
    }
}
export const reportPresets = [{key:'today',label:'Today'},{key:'yesterday',label:'Yesterday'},{key:'week',label:'This week'},{key:'last_week',label:'Last week'},{key:'month',label:'This month'},{key:'last_month',label:'Last month'},{key:'30',label:'Last 30 days'},{key:'90',label:'Last 90 days'},{key:'year',label:'This year'},{key:'custom',label:'Custom range'}];
export function reportPresetKey(from,to,today) {return reportPresets.find(preset=>{const range=reportPreset(preset.key,today);return range?.[0]===from && range?.[1]===to;})?.key || 'custom';}
export function validReportRange(from,to) {return /^\d{4}-\d{2}-\d{2}$/.test(from) && /^\d{4}-\d{2}-\d{2}$/.test(to) && from<=to && (parseDay(to)-parseDay(from))/86400000<=366;}
export function reportNavigationFilters(filters, target, catalog) {
    const allowed=catalog[target]?.filters || [];
    return {...filters, staff_ids:allowed.includes('staff') ? filters.staff_ids : [], service_ids:allowed.includes('service') ? filters.service_ids : [], statuses:allowed.includes('status') ? filters.statuses : [], method:allowed.includes('method') ? filters.method : '', search:'',dimension:'',dimension_value:'', page:1, sort:undefined, direction:undefined};
}
