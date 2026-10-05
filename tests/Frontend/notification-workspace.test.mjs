import test from 'node:test';
import assert from 'node:assert/strict';
import { deliveryLabel, invalidVariables, previewText, smsEstimate } from '../../resources/js/Support/notificationWorkspace.js';
test('delivery distinguishes provider acceptance, confirmation, simulation and suppression',()=>{
 assert.equal(deliveryLabel({status:'sent'}),'Sent');assert.equal(deliveryLabel({status:'delivered'}),'Delivered');assert.equal(deliveryLabel({status:'delivered',simulated:true}),'Simulated');assert.equal(deliveryLabel({status:'suppressed'}),'Not sent');
});
test('preview replaces only supported values and never executes markup',()=>{assert.equal(previewText('Hi {{ client_name }}, {{service_name}}',{client_name:'Sarah',service_name:'<script>x</script>'}),'Hi Sarah, <script>x</script>');assert.equal(previewText('{{client_name}}',{client_name:'Sarah'},true),'{{client_name}}');});
test('malformed or unsupported variable tokens are blocked',()=>{assert.deepEqual(invalidVariables('{{client_name}} {{internal_note}} {{client-name}}',['client_name']),['internal_note','incomplete variable']);assert.deepEqual(invalidVariables('{{ client_name }}',['client_name']),[]);});
test('SMS estimate handles GSM extensions and Unicode boundaries',()=>{assert.deepEqual(smsEstimate('a'.repeat(160)),{units:160,segments:1,encoding:'GSM-7'});assert.equal(smsEstimate('a'.repeat(161)).segments,2);assert.equal(smsEstimate('^'.repeat(80)).segments,1);assert.equal(smsEstimate('^'.repeat(81)).segments,2);assert.equal(smsEstimate('😊'.repeat(35)).segments,1);assert.equal(smsEstimate('😊'.repeat(36)).segments,2);assert.equal(smsEstimate('').segments,0);});
