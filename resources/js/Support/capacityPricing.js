export function capacityEstimate(rates, locations, staff) {
    locations = Number(locations); staff = Number(staff);
    if (!rates || !Number.isInteger(locations) || !Number.isInteger(staff) || locations < 1 || locations > 1000 || staff < 1 || staff > 10000) return null;
    const amount = rates.base_minor + (locations - 1) * rates.location_minor + (staff - 1) * rates.staff_minor;
    if (!Number.isSafeInteger(amount)) return null;
    return { ...rates, locations, staff, total_minor: amount, sms_monthly_allowance: rates.sms_monthly_allowance * staff };
}

export function capacityAnnualSaving(market, locations, staff) {
    const monthly = capacityEstimate(market?.intervals?.monthly, locations, staff);
    const annual = capacityEstimate(market?.intervals?.annual, locations, staff);
    return monthly && annual && monthly.currency === annual.currency ? Math.max(0, monthly.total_minor * 12 - annual.total_minor) : 0;
}
