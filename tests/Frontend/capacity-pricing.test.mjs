import test from 'node:test';
import assert from 'node:assert/strict';
import { capacityEstimate, capacityAnnualSaving } from '../../resources/js/Support/capacityPricing.js';
const rates={ base_minor:2900, location_minor:1900, staff_minor:900, sms_monthly_allowance:100, currency:'USD' };
test('shows the complete recurring total and pooled allowance',()=>{const q=capacityEstimate(rates,3,20); assert.equal(q.total_minor,23800);assert.equal(q.sms_monthly_allowance,2000);});
test('rejects fractional empty negative and unsupported capacity',()=>{for(const n of ['',0,-1,1.5,10001,NaN]) assert.equal(capacityEstimate(rates,1,n),null);});
test('calculates savings from complete local annual and monthly totals',()=>{const annual={...rates,base_minor:29000,location_minor:19000,staff_minor:9000};assert.equal(capacityAnnualSaving({intervals:{monthly:rates,annual}},3,20),47600);assert.equal(capacityAnnualSaving({intervals:{monthly:rates,annual:{...annual,currency:'GBP'}}},3,20),0);});
