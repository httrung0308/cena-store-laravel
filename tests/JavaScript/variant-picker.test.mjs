import test from 'node:test';
import assert from 'node:assert/strict';
import { variantPicker } from '../../resources/js/components/variant-picker.js';
const variants=[{id:1,product_color_id:'a',size_value:'40',is_active:true,available_qty:1,effective_price:0},{id:2,product_color_id:'a',size_value:'40.5',is_active:true,available_qty:1,effective_price:100},{id:3,product_color_id:'b',size_value:'40.5',is_active:true,available_qty:1,effective_price:null},{id:4,product_color_id:'b',size_value:'40',is_active:false,available_qty:1,effective_price:5}];
test('color and decimal sizes are exact distinct variant identities',()=>{
  const picker=variantPicker({variants,initialColorId:'a'});
  picker.choose(2); assert.equal(picker.selected.size_value,'40.5');
  picker.changeColor('b'); assert.equal(picker.variantId,null);
  picker.choose(3); assert.equal(picker.selected.id,3); assert.equal(picker.priceAvailable,false);
  picker.choose(4); assert.equal(picker.selected,null);
});
test('zero-price is not mistaken for null',()=>{
  const picker=variantPicker({variants,initialColorId:'a'});picker.choose(1);
  assert.equal(picker.priceAvailable,true);assert.equal(picker.selected.effective_price,0);
});
