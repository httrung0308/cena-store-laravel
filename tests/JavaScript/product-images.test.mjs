import test from 'node:test';
import assert from 'node:assert/strict';
import { productImages } from '../../resources/js/components/product-images.js';
function fixture() { let i=0; const revoked=[]; const urlApi={createObjectURL:()=>`blob:${++i}`,revokeObjectURL:u=>revoked.push(u)};return {state:productImages({urlApi}),revoked}; }
test('file preview is revoked on source swap, remove and destroy',()=>{
  const {state,revoked}=fixture();
  const a=state.add(), b=state.add();
  state.setFile(a,{type:'image/png'}); state.setFile(b,{type:'image/jpeg'});
  state.setExternal(a,'https://example.invalid/image.png');
  assert.deepEqual(revoked,['blob:1']);
  state.remove(b); assert.deepEqual(revoked,['blob:1','blob:2']);
  assert.equal(state.images.length,1);
  state.destroy(); assert.equal(state.images.length,0);
});
test('thumbnail and stable identities persist through reorder; SVG not previewed',()=>{
  const {state}=fixture(); const a=state.add(), b=state.add();
  state.makeThumbnail(b); state.move(1,0);
  assert.equal(state.images[0].key,b); assert.equal(state.images[0].thumbnail,true);
  state.setFile(a,{type:'image/svg+xml'}); assert.equal(state.images[1].previewUrl,null);
});
