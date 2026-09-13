export function reportColumnLabel(column) {
    const labels = { source_id: 'Source reference', row_count: 'Records', count: 'Visits', drill: 'Details', location_id: 'Location', staff_id: 'Staff', service_id: 'Service', starts_at: 'Starts', ends_at: 'Ends', expected_minor: 'Expected value' };
    return labels[column] || column.replace(/_minor$|_percent$/g, '').replace(/_id$/, ' reference').replaceAll('_', ' ').replace(/^./, letter => letter.toUpperCase());
}

// ReportService exposes database UTC timestamps; bare SQL timestamps must not
// be interpreted in the viewer's device zone. ISO offsets remain authoritative.
export function reportDateTime(value, timeZone, locale) {
    const normalized = String(value).replace(' ', 'T');
    const instant = new Date(/[zZ]$|[+-]\d{2}:?\d{2}$/.test(normalized) ? normalized : `${normalized}Z`);
    if (Number.isNaN(instant.getTime())) return String(value);
    return new Intl.DateTimeFormat(locale, { timeZone, dateStyle: 'medium', timeStyle: 'short' }).format(instant);
}
