import test from 'node:test';
import assert from 'node:assert/strict';
import { clientInstant, clientDate, clientDay, clientMoney, clientInitials, clientCommunicationChannels } from '../../resources/js/Support/clientWorkspace.js';
test('UTC SQL aggregates and ISO timestamps represent the same instant',()=>assert.equal(clientInstant('2026-10-03 23:30:00').toISOString(),clientInstant('2026-10-03T23:30:00Z').toISOString()));
test('client dates use salon midnight and DST instead of the device zone',()=>{assert.match(clientDate('2026-10-03 23:30:00','Asia/Kolkata'),/4.*Oct|Oct.*4/);assert.match(clientDay('2026-11-01T05:30:00Z','America/New_York'),/1:30/);assert.equal(clientDay('2026-11-01T05:30:00Z','America/New_York'),clientDay('2026-11-01T06:30:00Z','America/New_York'));});
test('money honors zero, two and three decimal currencies without combining them',()=>{assert.equal(clientMoney(1200,'USD','en-US'),'$12.00');assert.equal(clientMoney(1200,'JPY','en-US'),'¥1,200');assert.equal(clientMoney(1200,'KWD','en-US'),'KWD\u00a01.200');});
test('long Unicode names and missing dates have stable compact labels',()=>{assert.equal(clientInitials('  Élodie  Montgomery-Wellington  '),'ÉM');assert.equal(clientInitials('陈 美'),'陈美');assert.equal(clientDate(null),'—');assert.equal(clientDate('bad date'),'—');});

test('legacy preference formats keep email defaults and explicit withdrawals without enabling SMS',()=>{assert.deepEqual(clientCommunicationChannels(null),['email']);assert.deepEqual(clientCommunicationChannels(['sms']),['email','sms']);assert.deepEqual(clientCommunicationChannels({email:false,sms:true,whatsapp:true}),['sms']);assert.deepEqual(clientCommunicationChannels({email:false,sms:false}),[]);});
