import test from 'node:test';
import assert from 'node:assert/strict';
import {dayGroups,attentionItems,localDateKey} from '../../resources/js/Support/dailyWorkspace.js';
const now=Date.parse('2026-10-02T10:00:00Z');
const event=(status,startsAt='2026-10-02T09:00:00Z',extra={})=>({id:status,status,startsAt,endsAt:'2026-10-02T09:30:00Z',...extra});
test('finished visits never reappear as now, next or attention',()=>{
 const events=['completed','no_show','cancelled_by_client','cancelled_by_shop','rescheduled'].map(s=>event(s,'2026-10-02T10:30:00Z',{unassigned:true,forms:{pending:1}}));
 assert.equal(dayGroups(events,now,true).finished.length,5);
 assert.equal(attentionItems(events,now,true).length,0);
});
test('actual service status stays visible after its scheduled end',()=>{
 const events=[event('in_service'),event('checked_in'),event('confirmed'),event('confirmed','2026-10-02T12:00:00Z'),event('confirmed','2026-10-02T12:01:00Z')];
 const groups=dayGroups(events,now,true);
 assert.equal(groups.now.length,2);assert.equal(groups.unresolved.length,1);assert.equal(groups.next.length,1);assert.equal(groups.later.length,1);
 assert.equal(attentionItems(events,now,true)[0].reasons[0],'Past scheduled finish');
});
test('urgency takes precedence over routine forms and confirmation',()=>{
 const events=[event('confirmed','2026-10-02T14:00:00Z',{forms:{pending:1}}),event('pending_confirmation','2026-10-02T13:00:00Z'),event('confirmed')];
 const items=attentionItems(events,now,true);assert.deepEqual(items.map(i=>i.priority),[0,2,3]);
});
test('another date never claims to be happening now or overdue',()=>{
 const events=[event('in_service'),event('confirmed')];const groups=dayGroups(events,now,false);
 assert.equal(groups.now.length,0);assert.equal(groups.unresolved.length,0);assert.equal(groups.later.length,2);assert.equal(attentionItems(events,now,false).length,0);
});
test('dates follow the location at midnight and during DST',()=>{
 assert.equal(localDateKey('2026-10-01T18:40:00Z','Asia/Kolkata'),'2026-10-02');
 assert.equal(localDateKey('2026-11-01T04:30:00Z','America/New_York'),'2026-11-01');
 assert.equal(localDateKey('2026-10-02T01:00:00Z','America/Los_Angeles'),'2026-10-01');
});
