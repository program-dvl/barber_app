import test from 'node:test';
import assert from 'node:assert/strict';
import { capacityEstimate, capacityAnnualSaving, capacityMonthlyEquivalent } from '../../resources/js/Support/capacityPricing.js';
const legacy = { base_minor:2900, location_minor:1900, staff_minor:900, sms_monthly_allowance:100, currency:'USD' };
const rates = { ...legacy, staff_tiers:[{up_to:4,unit_minor:900},{up_to:9,unit_minor:600},{up_to:null,unit_minor:400}] };
const annual = { ...rates, base_minor:29000,location_minor:19000,staff_minor:9000,staff_tiers:rates.staff_tiers.map(t=>({...t,unit_minor:t.unit_minor*10})) };
for (const [locations,staff,total] of [[1,1,2900],[1,2,3800],[1,5,6500],[1,6,7100],[1,10,9500],[1,11,9900],[1,20,13500],[4,20,19200],[5,50,33100]]) {
    test(`public and owner estimate ${locations} locations / ${staff} staff`,()=> {
        const q=capacityEstimate(rates,locations,staff);
        assert.equal(q.total_minor,total);
        assert.equal(q.sms_monthly_allowance,staff*100);
        assert.equal(capacityEstimate(annual,locations,staff).total_minor,total*10);
        assert.equal(capacityAnnualSaving({intervals:{monthly:rates,annual}},locations,staff),total*2);
    });
}
test('progressive staff breakdown is charged by band',()=>assert.deepEqual(capacityEstimate(rates,1,20).staff_lines.map(l=>[l.quantity,l.unit_minor,l.total_minor]),[[4,900,3600],[5,600,3000],[10,400,4000]]));
test('preserves retained flat rates and 2000 credits',()=>{const q=capacityEstimate(legacy,3,20);assert.equal(q.total_minor,23800);assert.equal(q.sms_monthly_allowance,2000);});
test('rejects fractional empty negative and unsupported capacity',()=>{for(const n of ['',0,-1,1.5,10001,NaN,Infinity]) assert.equal(capacityEstimate(rates,1,n),null);for(const n of ['',0,-1,1.5,1001,NaN]) assert.equal(capacityEstimate(rates,n,1),null);});
test('rounds the display equivalent without changing the annual charge',()=>{assert.equal(capacityMonthlyEquivalent(29000),2417);assert.equal(capacityMonthlyEquivalent(135000),11250);assert.equal(capacityMonthlyEquivalent(-1),null);});
test('does not compare currencies',()=>assert.equal(capacityAnnualSaving({intervals:{monthly:rates,annual:{...annual,currency:'GBP'}}},1,20),0));
