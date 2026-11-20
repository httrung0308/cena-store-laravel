import test from 'node:test';
import assert from 'node:assert/strict';
import { galleryController } from '../../resources/js/components/gallery.js';
test('changing color resets image id, missing color has empty gallery',()=>{
  const g=galleryController({albums:{a:[{id:'a1',url:'/a1'},{id:'a2',url:'/a2'}],b:[{id:'b1',url:'/b1'}]},initialColor:'a'});
  g.choose('a2'); assert.equal(g.selectedId,'a2');
  g.changeColor('b'); assert.equal(g.selectedId,'b1');
  g.changeColor('none'); assert.equal(g.selected,null); assert.deepEqual(g.images,[]);
});
test('Arrow keys select only existing ids',()=>{
  const g=galleryController({albums:{a:[{id:'one',url:'/one'},{id:'two',url:'/two'}]},initialColor:'a'});
  let calls=0;g.onKeydown({key:'ArrowRight',preventDefault(){calls++}});
  assert.equal(g.selectedId,'two');g.onKeydown({key:'ArrowLeft',preventDefault(){calls++}});
  assert.equal(g.selectedId,'one');assert.equal(calls,2);
});
test('lightbox opens only when an image exists and Escape closes it',()=>{
  const six=Array.from({length:6},(_,i)=>({id:i+1,url:`/img-${i+1}.jpg`}));
  const g=galleryController({albums:{a:six},initialColor:'a'});
  assert.equal(g.images.length,6);
  assert.equal(g.currentIndex,1);
  g.openLightbox(); assert.equal(g.lightboxOpen,true);
  g.onKeydown({key:'Escape',preventDefault(){}});
  assert.equal(g.lightboxOpen,false);

  const empty=galleryController({albums:{a:[]},initialColor:'a'});
  empty.openLightbox();
  assert.equal(empty.lightboxOpen,false);
});
