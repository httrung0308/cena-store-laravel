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
test('sizeMatrix separates available, out_of_stock, and unavailable combinations and quantity clamps to maxQty',()=>{
  const matrixVariants=[
    {id:10,product_color_id:'a',size_value:'39',is_active:true,available_qty:4,max_cart_qty:4,effective_price:1500000,price_label:'1.500.000 ₫'},
    {id:11,product_color_id:'a',size_value:'40',is_active:true,available_qty:0,max_cart_qty:0,effective_price:1500000,price_label:'1.500.000 ₫'},
    {id:12,product_color_id:'b',size_value:'40',is_active:true,available_qty:8,max_cart_qty:8,effective_price:1500000,price_label:'1.500.000 ₫'},
    {id:13,product_color_id:'b',size_value:'41',is_active:true,available_qty:2,max_cart_qty:2,effective_price:1800000,price_label:'1.800.000 ₫'},
  ];
  const picker=variantPicker({variants:matrixVariants,initialColorId:'a',maxPerVariant:10});
  assert.deepEqual(picker.sizeMatrix.map(s=>[s.size_value,s.status]),[['39','available'],['40','out_of_stock'],['41','unavailable']]);
  assert.equal(picker.colorSummary.varies,false);

  picker.choose(10);
  picker.stepQuantity(10);
  assert.equal(picker.quantity,4);
  picker.stepQuantity(-10);
  assert.equal(picker.quantity,1);

  picker.changeColor('b');
  assert.equal(picker.variantId,null);
  assert.equal(picker.colorSummary.varies,true);
  assert.equal(picker.colorSummary.minPriceLabel,'1.500.000 ₫');
  assert.equal(picker.colorSummary.maxPriceLabel,'1.800.000 ₫');
  assert.deepEqual(picker.sizeMatrix.map(s=>[s.size_value,s.status]),[['39','unavailable'],['40','available'],['41','available']]);
});
