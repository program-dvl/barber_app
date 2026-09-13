import test from 'node:test';
import assert from 'node:assert/strict';
import { reportColumnLabel, reportDateTime } from '../../resources/js/Support/reportPresentation.js';

test('database timestamps display in the report zone, matching the calendar', () => {
    assert.equal(reportDateTime('2026-09-17 04:15:00', 'Asia/Kolkata', 'en-GB'), '17 Sept 2026, 09:45');
});
test('explicit offsets are preserved and daylight saving is respected', () => {
    assert.equal(reportDateTime('2026-07-01T12:00:00+02:00', 'Europe/London', 'en-GB'), '1 Jul 2026, 11:00');
    assert.equal(reportDateTime('2026-01-01 10:00:00', 'Europe/London', 'en-GB'), '1 Jan 2026, 10:00');
});
test('invalid timestamps remain legible and currency columns hide storage units', () => {
    assert.equal(reportDateTime('Unavailable', 'Asia/Kolkata', 'en-GB'), 'Unavailable');
    assert.equal(reportColumnLabel('collected_minor'), 'Collected');
    assert.equal(reportColumnLabel('source_id'), 'Source reference');
});
