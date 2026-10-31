import test from 'node:test';
import assert from 'node:assert/strict';
import { colorVariants } from '../../resources/js/components/color-variants.js';
test('size 40 and 40.5 retain identity under reorder and remove', () => {
  const state = colorVariants({ colors: [{id:1,variants:[{id:11,size_value:'40'},{id:12,size_value:'40.5'}]}, {id:2,variants:[]}] });
  const a = state.colors[0], b = state.colors[1];
  const ids = a.variants.map(x => x.uid);
  state.moveVariant(a.uid,0,1);
  assert.deepEqual(a.variants.map(x=>x.id),[12,11]);
  state.removeVariant(a.uid,ids[0]);
  assert.equal(a.variants.find(x=>x.id===11).removed,true);
  assert.deepEqual(b.variants,[]);
  assert.equal(a.variants.find(x=>x.id===12).size_value,'40.5');
});
test('client ids are independent of server ids and color reorder preserves children', () => {
  const state = colorVariants({colors:[{id:7,variants:[{id:55,size_value:'00040'}]}]});
  const created = state.addColor({name:'New'});
  state.addSequence(created,['40','40.5']);
  state.moveColor(0,1);
  assert.equal(state.colors[0].uid,created);
  assert.equal(state.colors[0].variants[1].size_value,'40.5');
  assert.equal(state.colors[1].variants[0].id,55);
  assert.equal(state.colors[1].variants[0].size_value,'00040');
});
