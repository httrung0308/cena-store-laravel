import test from 'node:test';
import assert from 'node:assert/strict';
import { addressSelector } from '../../resources/js/components/address-selector.js';
const provinces = [
  { code: '01', name: 'Tỉnh thử 1', dataset_version: 'synthetic' },
  { code: '02', name: 'Tỉnh thử 2', dataset_version: 'synthetic' },
];
function deferred() { let resolve, reject; const promise = new Promise((a,b) => { resolve = a; reject = b; }); return {promise,resolve,reject}; }
test('out-of-order province responses do not overwrite newest code or ward', async () => {
  const req = new Map(); const state = addressSelector({ provinces, getWards: c => { const d = deferred(); req.set(c,d); return d.promise; } });
  const one = state.changeProvince('01');
  const two = state.changeProvince('02');
  req.get('02').resolve([{code:'00002',name:'Xã thử',parent_code:'02',dataset_version:'synthetic'},{code:'99999',name:'Wrong parent',parent_code:'01',dataset_version:'synthetic'}]);
  await two;
  state.selectWard('00002');
  req.get('01').resolve([{code:'00001',name:'Old',parent_code:'01',dataset_version:'synthetic'}]);
  await one;
  assert.equal(state.provinceCode, '02'); assert.equal(state.wardCode, '00002'); assert.deepEqual(state.wards.map(w=>w.code),['00002']);
  state.destroy();
});
test('select clears stale ward and absent provider makes form unavailable', async () => {
  const state = addressSelector({ provinces, initialProvinceCode:'01',initialWardCode:'00001' });
  assert.equal(state.available,false);
  await state.changeProvince('02');
  assert.equal(state.wardCode,null); assert.deepEqual(state.wards,[]);
});
test('provider errors allow retry without inventing data', async () => {
  let attempts = 0;
  const state = addressSelector({ provinces, getWards: async code => {if (++attempts === 1) throw new Error('offline'); return [{code:'00001',name:'Valid',parent_code:code,dataset_version:'synthetic'}];} });
  await state.changeProvince('01'); assert.match(state.error,/Không thể tải/);
  await state.retry(); assert.equal(state.error,null); assert.deepEqual(state.wards.map(w=>w.code),['00001']);
});
