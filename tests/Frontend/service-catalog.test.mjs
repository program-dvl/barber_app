import test from 'node:test';
import assert from 'node:assert/strict';
import { decimalToMinor, durationLabel, filterServices, channelLabel, serviceVariantAt, calendarServiceChoices, effectiveCatalogPreview } from '../../resources/js/Support/serviceCatalog.js';
const rows = [
 {name:'Signature cut',kind:'service',category:'Hair',category_id:'hair',category_order:2,is_active:true,online_visible:true,price_minor:150000,duration_minutes:45,processing_minutes:0,cleanup_minutes:5,staff:[{public_id:'alex'}],locations:[{public_id:'north'}],warnings:[],updated_at:'2026-10-03'},
 {name:'Conditioning',kind:'addon',category:'Treatment',category_id:'treatment',category_order:1,is_active:true,online_visible:false,price_minor:30000,duration_minutes:15,processing_minutes:20,cleanup_minutes:0,staff:[],locations:[],warnings:['No staff'],updated_at:'2026-10-04'},
 {name:'Seasonal facial',kind:'service',category:null,category_id:null,category_order:65535,is_active:false,online_visible:true,price_minor:200000,duration_minutes:60,processing_minutes:0,cleanup_minutes:0,staff:[],locations:[],warnings:[],updated_at:'2026-09-01'},
];
test('formats visit spans naturally, including zero buffer', () => { assert.equal(durationLabel(0),'0 min'); assert.equal(durationLabel(60),'1 hr'); assert.equal(durationLabel(90),'1 hr 30 min'); assert.equal(durationLabel(45),'45 min'); });
test('converts price exactly and refuses negative, invalid, overprecise and exponential values', () => { assert.equal(decimalToMinor('1500.01'),150001); assert.equal(decimalToMinor('0'),0); assert.equal(decimalToMinor('0.29'),29); for(const value of ['','-1','1e3','0.001','a','1,000']) assert.equal(decimalToMinor(value),null); });
test('combines category search, channel, staff and branch without hiding active free prices', () => { assert.deepEqual(filterServices(rows,{search:'Hair',staff:'alex',location:'north',channel:'online'}).map(s=>s.name),['Signature cut']); assert.deepEqual(filterServices(rows,{status:'setup'}).map(s=>s.name),['Conditioning']); assert.deepEqual(filterServices(rows,{status:'inactive',category:'uncategorised'}).map(s=>s.name),['Seasonal facial']); });
test('sorts by persisted category order, full calendar span and price', () => { assert.equal(filterServices(rows,{sort:'category'})[0].name,'Conditioning'); assert.equal(filterServices(rows,{sort:'duration'})[0].name,'Conditioning'); assert.equal(filterServices(rows,{sort:'price'})[2].name,'Seasonal facial'); });
test('channel labels do not imply bookability for incomplete or inactive records', () => { assert.equal(channelLabel(rows[0]),'Online enabled'); assert.equal(channelLabel(rows[1]),'Needs setup'); assert.equal(channelLabel(rows[2]),'Inactive'); });

test('Calendar respects dated staff variants, exclusive end bounds and attached add-on parents', () => {
 const service = {public_id:'cut',kind:'service',addon_ids:['boost'],staff_variants:[{staff:'alex',duration_minutes:40,effective_from:null,effective_until:'2035-10-10T10:00:00'},{staff:'alex',duration_minutes:50,effective_from:'2035-10-10T10:00:00',effective_until:null}]};
 assert.equal(serviceVariantAt(service,'alex','2035-10-10T09:59').duration_minutes,40);
 assert.equal(serviceVariantAt(service,'alex','2035-10-10T10:00').duration_minutes,50);
 assert.equal(serviceVariantAt(service,'unqualified','2035-10-10T10:00'),null);
 const addon = {public_id:'boost',kind:'addon'};
 assert.equal(calendarServiceChoices([service,addon],[],'2035-10-10T10:00').length,1);
 assert.equal(calendarServiceChoices([service,addon],[{service:'cut'}],'2035-10-10T10:00').length,2);
});

test('booking estimates preserve staff then location then base price and include zero overrides', () => {
 const service = {active_minutes:45,processing_minutes:20,cleanup_minutes:5,price_minor:150000};
 assert.deepEqual(effectiveCatalogPreview(service,{price_minor:175000,duration_minutes:40,processing_minutes:0},160000),{price_minor:175000,visit_minutes:40,bookable_minutes:45});
 assert.equal(effectiveCatalogPreview(service,null,160000).price_minor,160000);
 assert.equal(effectiveCatalogPreview(service,{price_minor:0},160000).price_minor,0);
 assert.equal(effectiveCatalogPreview(service,null).price_minor,150000);
});
