import test from 'node:test';
import assert from 'node:assert/strict';
import {canMove,dateKey,dayInterval,layoutEntries,matchesSearch,staffEntries,subtractBusy} from '../../resources/js/Support/calendarWorkspace.js';
const range=(from,until,extra={})=>({startsAt:`2026-10-03T${from}:00Z`,endsAt:`2026-10-03T${until}:00Z`,...extra});
test('hidden bookings, breaks and holds protect gaps; replacement excludes only its original visit',()=>{
 const working=[range('09:00','13:00')];
 const busy=[range('09:30','10:00',{appointmentId:'visit'}),range('10:45','11:15',{kind:'hold'}),range('11:00','12:00',{kind:'break'})];
 assert.deepEqual(subtractBusy(working,busy).map(r=>[r.startsAt.slice(11,16),r.endsAt.slice(11,16)]),[['09:00','09:30'],['10:00','10:45'],['12:00','13:00']]);
 assert.equal(subtractBusy(working,busy,'visit')[0].endsAt,'2026-10-03T10:45:00.000Z');
});
test('UTC capacity subtraction remains exact across the repeated DST hour',()=>{
 const free=subtractBusy([{startsAt:'2026-11-01T00:00:00-04:00',endsAt:'2026-11-01T03:00:00-05:00'}],[{startsAt:'2026-11-01T01:15:00-04:00',endsAt:'2026-11-01T01:15:00-05:00'}]);
 assert.equal(Date.parse(free[0].endsAt)-Date.parse(free[0].startsAt),75*60000);
 assert.equal(Date.parse(free[1].endsAt)-Date.parse(free[1].startsAt),105*60000);
});
test('overnight appointments clip to their day and a midnight end leaves no phantom visit',()=>{
 const event={startsAt:'2026-10-02T22:00:00+05:30',endsAt:'2026-10-03T02:00:00+05:30'};
 assert.deepEqual(dayInterval(event,'2026-10-03','Asia/Kolkata'),{start:0,end:120});
 assert.equal(dayInterval({...event,endsAt:'2026-10-03T00:00:00+05:30'},'2026-10-03','Asia/Kolkata'),null);
 assert.equal(dateKey('2026-10-02T18:40:00Z','Asia/Kolkata'),'2026-10-03');
});
test('connected overlaps receive independent lanes while adjacent visits use the full column',()=>{
 const entries=[range('09:00','10:00',{id:'a'}),range('09:15','09:45',{id:'b'}),range('10:00','10:30',{id:'c'})];
 const cards=layoutEntries(entries,'2026-10-03','UTC',540,660);
 assert.equal(cards[0].style.width,'calc(50% - 8px)');assert.equal(cards[1].style.left,'calc(50% + 4px)');assert.equal(cards[2].style.width,'calc(100% - 8px)');
 assert.ok(cards.every(c=>Number.isFinite(parseFloat(c.style.top)) && c.height>0));
});
test('processing segments release staff without hiding the client appointment',()=>{
 const event=range('09:00','10:30',{id:'a',staff:[{id:'stylist'}],segments:[range('09:00','09:30',{staffId:'stylist',occupiesStaff:true}),range('09:30','10:00',{staffId:'stylist',occupiesStaff:false}),range('10:00','10:30',{staffId:'stylist',occupiesStaff:true})]});
 const cards=staffEntries([event],'stylist');assert.equal(cards.length,3);assert.equal(cards[1].processing,true);assert.equal(cards[1].originalStartsAt,event.startsAt);assert.equal(staffEntries([event],'other').length,0);
});
test('search handles long names, staff, service, reference and formatted phone safely',()=>{
 const event={title:'Alexandra Catherine Montgomery',clientMobile:'+1 (212) 555-0199',reference:'APT-2001',services:[{name:'Precision haircut'}],staff:[{name:'Aria'}]};
 for(const query of ['montgomery','aria','PRECISION','apt-2001','2125550199']) assert.equal(matchesSearch(event,query),true);
 assert.equal(matchesSearch({...event,clientMobile:null},'2125550199'),false);
});
test('dragging requires an allowed mutable appointment and never moves running or finished visits',()=>{
 assert.equal(canMove(null),false);assert.equal(canMove({status:'confirmed',canManage:true}),true);
 for(const status of ['in_service','completed','cancelled_by_shop','no_show','rescheduled']) assert.equal(canMove({status,canManage:true}),false);
 assert.equal(canMove({status:'confirmed',canManage:false}),false);
});
