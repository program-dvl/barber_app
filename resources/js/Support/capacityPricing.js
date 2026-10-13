export function capacityEstimate(rates, locations, staff) {
    locations = Number(locations); staff = Number(staff);
    if (!rates || !Number.isInteger(locations) || !Number.isInteger(staff) || locations < 1 || locations > 1000 || staff < 1 || staff > 10000) return null;
    const tiers = rates.staff_tiers || [{ up_to: null, unit_minor: rates.staff_minor }];
    const included = rates.included_staff ?? 1;
    let previous = 0;
    const staff_lines = [];
    for (const tier of tiers) {
        const quantity = Math.max(0, Math.min(staff - included, tier.up_to ?? staff - included) - previous);
        if (quantity) staff_lines.push({ from: previous + included + 1, to: previous + quantity + included, quantity, unit_minor: tier.unit_minor, total_minor: quantity * tier.unit_minor });
        previous = tier.up_to ?? staff - included;
    }
    const amount = rates.base_minor + Math.max(0, locations - (rates.included_locations ?? 1)) * rates.location_minor + staff_lines.reduce((sum, line) => sum + line.total_minor, 0);
    if (!Number.isSafeInteger(amount)) return null;
    return { ...rates, locations, staff, staff_lines, total_minor: amount, sms_monthly_allowance: rates.sms_monthly_allowance * staff };
}

// Round only the displayed equivalent, never the annual amount charged.
export function capacityMonthlyEquivalent(annualMinor) {
    return Number.isSafeInteger(annualMinor) && annualMinor >= 0 ? Math.trunc((annualMinor + 6) / 12) : null;
}

export function capacityAnnualSaving(market, locations, staff) {
    const monthly = capacityEstimate(market?.intervals?.monthly, locations, staff);
    const annual = capacityEstimate(market?.intervals?.annual, locations, staff);
    return monthly && annual && monthly.currency === annual.currency ? Math.max(0, monthly.total_minor * 12 - annual.total_minor) : 0;
}
