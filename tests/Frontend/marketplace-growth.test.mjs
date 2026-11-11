import test from 'node:test';
import assert from 'node:assert/strict';
import { acquisitionConsent, acquisitionLabels, ACQUISITION_CONSENT_KEY } from '../../resources/js/Support/marketplaceAttribution.js';
const now = 1794096000000;
function browser(record, general = null, signal = {}) {
    return {navigator:signal,localStorage:{getItem:key=>JSON.stringify(key===ACQUISITION_CONSENT_KEY?record:general)}};
}
const accepted = {version:'mpg-consent-v1',choice:'accepted',expires_at:now+86400000};
test('acquisition remains unknown until its separate explicit consent, including earlier Google consent',()=>{
    assert.equal(acquisitionConsent(browser(null,{choice:'accepted',expires_at:now+86400000}),now),false);
    assert.equal(acquisitionConsent(browser(accepted),now),true);
    assert.equal(acquisitionConsent(browser({...accepted,choice:'rejected'}),now),false);
});
test('privacy signals general analytics rejection expiry and invalid storage all prevent acquisition',()=>{
    assert.equal(acquisitionConsent(browser(accepted,null,{globalPrivacyControl:true}),now),false);
    assert.equal(acquisitionConsent(browser(accepted,null,{doNotTrack:'1'}),now),false);
    assert.equal(acquisitionConsent(browser(accepted,{choice:'rejected',expires_at:now+86400000}),now),false);
    assert.equal(acquisitionConsent(browser({...accepted,expires_at:now-1}),now),false);
    assert.equal(acquisitionConsent(browser({...accepted,expires_at:now+31*86400000}),now),false);
    assert.equal(acquisitionConsent({navigator:{},localStorage:{getItem(){throw Error('blocked')}}},now),false);
});
test('directory navigation and direct provider visits are different evidence',()=>{
    assert.deepEqual(acquisitionLabels({url:'https://example.test/marketplace?q=private',kind:'city'}),{source:'marketplace_discovery',owner_source:'unknown'});
    assert.deepEqual(acquisitionLabels({url:'https://example.test/marketplace/salons/public',kind:'profile'}),{source:'direct',owner_source:'unknown'});
});
test('owner shared links do not become Marketplace acquisition and arbitrary UTM text is discarded',()=>{
    assert.deepEqual(acquisitionLabels({url:'https://example.test/book/public?utm_source=whatsapp&email=private@example.test',kind:'profile'}),{source:'owner_link',owner_source:'whatsapp'});
    assert.deepEqual(acquisitionLabels({url:'https://example.test/book/public?utm_source=secret-campaign',kind:'profile'}),{source:'direct',owner_source:'unknown'});
});
test('external search classification retains neither referrer URL nor search phrases',()=>{
    const result=acquisitionLabels({url:'https://example.test/marketplace',referrer:'https://www.google.com/search?q=private+phrase',kind:'entry'});
    assert.deepEqual(result,{source:'external_search',owner_source:'unknown'});
    assert.doesNotMatch(JSON.stringify(result),/private|phrase|google\.com|search\?/);
    assert.deepEqual(acquisitionLabels({url:'https://example.test/book/public',referrer:'https://google.com.attacker.example/search?q=private',kind:'profile'}),{source:'direct',owner_source:'unknown'});
});
