import test from 'node:test';
import assert from 'node:assert/strict';
import { shiftDate, slotGroups, eligibleService, safeRead, safeWrite } from '../../resources/js/Support/publicBooking.js';

test('location date arithmetic crosses DST, year and leap boundaries without shifting dates', () => {
    assert.equal(shiftDate('2026-03-08', 1), '2026-03-09');
    assert.equal(shiftDate('2026-11-01', -1), '2026-10-31');
    assert.equal(shiftDate('2026-12-31', 1), '2027-01-01');
    assert.equal(shiftDate('2028-02-28', 1), '2028-02-29');
});
test('slots group by appointment location, including repeated clock-change times', () => {
    const slots = ['2026-11-01T05:30:00Z','2026-11-01T06:30:00Z','2026-11-01T18:00:00Z','2026-11-01T23:00:00Z'].map(starts_at_utc => ({ starts_at_utc }));
    const result = slotGroups(slots, 'America/New_York');
    assert.deepEqual(result.map(item => [item.label, item.slots.length]), [['Morning',2],['Afternoon',1],['Evening',1]]);
});
test('business and service client restrictions intersect', () => {
    assert.equal(eligibleService({ client_eligibility:'existing' }, 'new', { online_new_client_rule:'allow' }), false);
    assert.equal(eligibleService({ client_eligibility:'all', consultation_required:false }, 'new', { online_new_client_rule:'consultation_only' }), false);
    assert.equal(eligibleService({ client_eligibility:'all', consultation_required:true }, 'new', { online_new_client_rule:'consultation_only' }), true);
    assert.equal(eligibleService({ client_eligibility:'all' }, 'new', { online_new_client_rule:'existing_only' }), false);
});
test('blocked or corrupted browser storage cannot break guest booking', () => {
    const blocked = { getItem(){throw Error('blocked');}, setItem(){throw Error('quota');}, removeItem(){throw Error('blocked');} };
    assert.equal(safeRead('flow', blocked), null);
    assert.doesNotThrow(() => safeWrite('flow', { flow:'abc' }, blocked));
    assert.doesNotThrow(() => safeWrite('flow', null, blocked));
    assert.equal(safeRead('flow', { getItem: () => '{broken' }), null);
});
