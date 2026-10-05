import test from 'node:test';
import assert from 'node:assert/strict';
import { elapsedMinutes, estimateLabel, localInput, matchesQueue, pastQuote, waitLabel } from '../../resources/js/Support/walkInWorkspace.js';
const now = Date.parse('2026-10-03T06:00:00Z');
test('wait clocks clamp future arrivals and stay readable over multiple days',()=>{
    assert.equal(elapsedMinutes('2026-10-03T05:43:00Z',now),17);
    assert.equal(elapsedMinutes('2026-10-03T07:00:00Z',now),0);
    assert.equal(waitLabel(67),'1h 7m'); assert.equal(waitLabel(2881),'2d 0h');
});
test('advisory estimates use ranges and stop promising availability after stale refreshes',()=>{
    assert.equal(estimateLabel('2026-10-03T06:17:00Z',now,new Date(now).toISOString()),'15–20 min');
    assert.equal(estimateLabel('2026-10-03T06:00:00Z',now,new Date(now).toISOString()),'Next available');
    assert.equal(estimateLabel(null,now,new Date(now).toISOString()),'Check availability');
    assert.equal(estimateLabel('2026-10-03T06:17:00Z',now,'2026-10-03T05:57:00Z'),'Refresh estimate');
});
test('attention uses the recorded quote and stops at service start',()=>{
    assert.equal(pastQuote({status:'waiting',original_estimated_at:'2026-10-03T05:59:00Z'},now),true);
    assert.equal(pastQuote({status:'in_service',original_estimated_at:'2026-10-03T05:59:00Z'},now),false);
    assert.equal(pastQuote({status:'waiting',original_estimated_at:null},now),false);
});
test('queue search covers service staff and formatted phone without reordering entries',()=>{
    const entry={client_name:'Alex Example',client_mobile:'+91 91234 56789',service_name:'Signature cut',preferred_staff_name:'Mina'};
    assert.equal(matchesQueue(entry,'mina'),true);assert.equal(matchesQueue(entry,'signature'),true);assert.equal(matchesQueue(entry,'9123456789'),true);assert.equal(matchesQueue(entry,'Noah'),false);
});
test('arrival input follows the branch even when the device is in another zone',()=>{
    assert.equal(localInput(now,'Asia/Kolkata'),'2026-10-03T11:30');assert.equal(localInput(now,'America/New_York'),'2026-10-03T02:00');
});
