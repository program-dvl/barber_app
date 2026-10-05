import test from 'node:test';
import assert from 'node:assert/strict';
import {parseMoney,moneyInput,tenderSummary,basketPayload,pendingStorageKey,readPending} from '../../resources/js/Support/checkoutWorkspace.js';
test('money entry is precise and respects currency digits',()=>{
    assert.equal(parseMoney('120.05','INR'),12005);assert.equal(parseMoney('1.001','KWD'),1001);assert.equal(parseMoney('120','JPY'),120);
    for(const invalid of ['-1','1e3','1,000','NaN','1.001',''])assert.equal(parseMoney(invalid,'INR'),null);
    assert.equal(parseMoney('12.00','JPY'),null);assert.equal(moneyInput(1001,'KWD'),'1.001');
});
test('partial and split entries do not claim settlement before the balance matches',()=>{
    assert.deepEqual(tenderSummary([{amount_minor:5000},{amount_minor:3000}],10000),{total:8000,remaining:2000,valid:true});
    assert.equal(tenderSummary([{amount_minor:12000}],10000).valid,false);assert.equal(tenderSummary([{amount_minor:0}],10000).valid,false);
});
test('basket sends identifiers and intentional overrides without trusting descriptions or taxes',()=>{
    const payload=basketPayload([{booked_line_id:4,description:'Changed',quantity:1,staff_profile_id:2,unit_price_minor:4000,tax_rate_bps:1800,discount_minor:0}],[],null,true);
    assert.equal(payload.items[0].unit_price_minor,undefined);assert.equal(payload.items[0].description,undefined);assert.equal(payload.items[0].tax_rate_bps,undefined);
    assert.equal(basketPayload([{quantity:1,overridden:true,unit_price_minor:0}],[],'Free correction',false).items[0].unit_price_minor,0);
});
test('an uncertain command keeps its original key, scope and exact payment payload on reload',()=>{
    const command={idempotency_key:'original',received_confirmed:true,payments:[{method:'cash',amount_minor:2000}]};
    const store={getItem:()=>JSON.stringify(command)};assert.deepEqual(readPending(store,'key'),command);
    assert.notEqual(pendingStorageKey(1,'A','S'),pendingStorageKey(2,'A','S'));assert.notEqual(pendingStorageKey(1,'A','S'),pendingStorageKey(1,'B','S'));
    assert.equal(readPending({getItem:()=>'{broken'},'key'),null);
});
