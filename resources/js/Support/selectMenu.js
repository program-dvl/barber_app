export function nextEnabledOption(options, current, direction) {
    if (!options.length) return -1;
    for (let step = 1; step <= options.length; step++) {
        const index = ((current + direction * step) % options.length + options.length) % options.length;
        if (!options[index].disabled) return index;
    }
    return -1;
}

export function selectMenuPosition(rect, viewport) {
    const margin = 8;
    const gap = 6;
    const width = Math.min(Math.max(rect.width, 240), viewport.width - margin * 2);
    const below = Math.max(0, viewport.height - rect.bottom - gap - margin);
    const above = Math.max(0, rect.top - gap - margin);
    const upwards = below < 220 && above > below;
    return {
        left: Math.max(margin, Math.min(rect.left, viewport.width - width - margin)),
        width,
        maxHeight: Math.min(360, upwards ? above : below),
        top: upwards ? null : rect.bottom + gap,
        bottom: upwards ? viewport.height - rect.top + gap : null,
    };
}
