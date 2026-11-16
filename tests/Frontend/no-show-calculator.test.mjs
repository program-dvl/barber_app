import test from 'node:test';
import assert from 'node:assert/strict';
import {appointmentValueMinor, missedAppointmentCount, noShowEstimate, noShowMoney} from '../../resources/js/Support/noShowCalculator.js';

test('example computes monthly, yearly and independently chosen scenario',()=>{
    const result=noShowEstimate('60','8',50);
    assert.equal(result.monthlyMinor,48000); assert.equal(result.annualMinor,576000);
    assert.equal(result.recoveryMonthlyMinor,24000); assert.equal(result.recoveryAppointments,4);
});
test('decimal currency math stays in integer minor units',()=>{
    assert.equal(appointmentValueMinor('19.99'),1999);
    assert.equal(noShowEstimate('19.99','3',25).monthlyMinor,5997);
    assert.equal(noShowEstimate('19.99','3',25).recoveryMonthlyMinor,1499);
    assert.equal(noShowEstimate('0.10','3',100).annualMinor,360);
});
test('zero no-shows and zero appointment value are valid zero outcomes',()=>{
    assert.equal(noShowEstimate('60','0').annualMinor,0);
    assert.equal(noShowEstimate('0','8').monthlyMinor,0);
    assert.equal(noShowEstimate('60','8',0).recoveryMonthlyMinor,0);
    assert.equal(noShowEstimate('60','8',100).recoveryMonthlyMinor,48000);
});
test('blank, negative, fractional counts and non-finite input never become misleading zeroes',()=>{
    for(const value of ['',null,undefined,'-1','2.50','2e2','Infinity','NaN','hello','100001']) assert.equal(missedAppointmentCount(value),null,String(value));
    for(const value of ['',null,undefined,'-1','1.234','1,000','Infinity','1e3','1000000.01']) assert.equal(appointmentValueMinor(value),null,String(value));
    assert.equal(noShowEstimate('','8'),null);
    assert.equal(noShowEstimate('60',''),null);
    for(const n of [-1,101,2.5,NaN]) assert.equal(noShowEstimate('60','8',n),null);
});
test('largest permitted inputs remain safely bounded for yearly money',()=>{
    const result=noShowEstimate('1000000','100000',100);
    assert.equal(result.annualMinor,120000000000000);
    assert.ok(Number.isSafeInteger(result.annualMinor));
});
test('currency changes formatting without altering monetary arithmetic',()=>{
    assert.equal(noShowMoney(48000,'USD'),'$480');
    assert.match(noShowMoney(48000,'INR'),/480/);
    assert.equal(noShowMoney(100,'INVALID'),'—');
    assert.equal(noShowMoney(-100,'USD'),'—');
    assert.equal(noShowMoney(1999,'USD'),'$19.99');
});

test('refinements exclude refilled slots before subtracting kept payments and avoided costs',()=>{
    const q=noShowEstimate('60','8',50,{refilled:'2',collected:'100',avoided:'10'});
    assert.equal(q.grossMonthlyMinor,48000); assert.equal(q.refilledValueMinor,12000);
    assert.equal(q.emptySlots,6); assert.equal(q.unpaidMonthlyMinor,26000);
    assert.equal(q.avoidedMonthlyMinor,6000); assert.equal(q.monthlyMinor,20000);
    assert.equal(q.annualMinor,240000); assert.equal(q.recoveryMonthlyMinor,10000);
    assert.equal(q.recoveryAppointments,3);
});
test('fully replaced, paid or cost-covered empty slots have no positive earnings at risk',()=>{
    assert.equal(noShowEstimate('60','8',100,{refilled:'8'}).monthlyMinor,0);
    assert.equal(noShowEstimate('60','8',100,{collected:'480'}).monthlyMinor,0);
    const covered=noShowEstimate('60','8',50,{collected:'500',avoided:'10'});
    assert.equal(covered.monthlyMinor,0); assert.equal(covered.recoveryMonthlyMinor,0);
    assert.equal(covered.covered,true);
});
test('invalid adjustments never silently become zero or exceed the missed count',()=>{
    for(const options of [{refilled:'9'},{refilled:'1.5'},{collected:'-1'},{avoided:'-1'},{collected:''},{avoided:'1.234'}]) assert.equal(noShowEstimate('60','8',50,options),null);
    assert.equal(appointmentValueMinor('.50'),50);
});
