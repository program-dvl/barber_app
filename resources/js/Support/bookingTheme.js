const channels = hex => [1, 3, 5].map(offset => parseInt(hex.slice(offset, offset + 2), 16));
const hexColor = values => `#${values.map(value => Math.round(value).toString(16).padStart(2, '0')).join('')}`;
const luminance = values => values.map(value => {
    const channel = value / 255;
    return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
}).reduce((total, value, index) => total + value * [0.2126, 0.7152, 0.0722][index], 0);

// Keep white button labels and coloured links readable on the light booking
// surfaces. Retain the exact saved accent for non-text decoration.
export function bookingTheme(color) {
    if (typeof color !== 'string' || !/^#[0-9a-f]{6}$/i.test(color)) return {};
    const rgb = channels(color);
    const tint = hexColor(rgb.map(value => value * 0.08 + 255 * 0.92));
    const lightSurface = Math.min(luminance(channels('#eef2f7')), luminance(channels(tint)));
    let action = color.toLowerCase();
    for (let percent = 99; (luminance(channels(action)) + 0.05) > (lightSurface + 0.05) / 4.6 && percent >= 0; percent--) {
        action = hexColor(rgb.map(value => value * percent / 100));
    }
    return {
        '--booking-accent': color.toLowerCase(),
        '--action-primary': action,
        '--action-primary-hover': hexColor(channels(action).map(value => value * 0.85)),
        '--focus-ring': action,
        '--status-info-soft': tint,
    };
}
