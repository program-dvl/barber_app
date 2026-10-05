export const variableLabel = name => ({ client_name:'Client name', business_name:'Business name', staff_name:'Professional', service_name:'Service', location_name:'Location', appointment_date:'Appointment date', appointment_time:'Appointment time', time_zone:'Appointment timezone', booking_reference:'Booking reference', amount:'Recorded amount', currency:'Currency', action_link:'Secure action link', queue_estimate:'Queue estimate' }[name] || name);
export function previewText(text, samples, raw = false) {
    return raw ? text : String(text || '').replace(/\{\{\s*([a-z_]+)\s*\}\}/g, (_, name) => samples[name] ?? `[${variableLabel(name)}]`);
}
export function invalidVariables(text, allowed) {
    const tokens = [...String(text).matchAll(/\{\{\s*([a-z_]+)\s*\}\}/g)];
    const unknown = tokens.map(match => match[1]).filter(name => !allowed.includes(name));
    const remaining = String(text).replace(/\{\{\s*[a-z_]+\s*\}\}/g, '');
    if (remaining.includes('{{') || remaining.includes('}}')) unknown.push('incomplete variable');
    return [...new Set(unknown)];
}
export function deliveryLabel(message) {
    if (message.simulated && ['sent','delivered'].includes(message.status)) return 'Simulated';
    return ({queued:'Queued', retried:'Retry scheduled', sending:'Sending', sent:'Sent', delivered:'Delivered', failed:'Failed', suppressed:'Not sent'}[message.status] || 'Unknown');
}
// GSM-7 extension characters consume two septets. UCS-2 uses UTF-16 code units.
// Estimates apply to rendered sample content; real values and carrier rules vary.
export function smsEstimate(text) {
    const basic = '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ\u001bÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';
    const extension = '^{}\\[~]|€\f';
    const unicode = [...text].some(char => !basic.includes(char) && !extension.includes(char));
    const units = unicode ? text.length : [...text].reduce((sum,char) => sum + (extension.includes(char) ? 2 : 1),0);
    const single = unicode ? 70 : 160;
    const concatenated = unicode ? 67 : 153;
    return { units, segments: units === 0 ? 0 : units <= single ? 1 : Math.ceil(units / concatenated), encoding:unicode ? 'Unicode' : 'GSM-7' };
}
