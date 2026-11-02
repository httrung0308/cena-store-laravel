import test from 'node:test';
import assert from 'node:assert/strict';
import { bindAddToCartButtons, cartStore, formatVnd } from '../../resources/js/components/cart.js';

const cart = (overrides = {}) => ({ version: 3, count: 2, subtotal: 3580000, items: [{ id: 7, variant_id: 11, quantity: 2, max_quantity: 5 }], notices: [], checkout_note: 'note', ...overrides });

function fakeFetch(responses) {
  const calls = [];
  const fetcher = async (url, options) => {
    calls.push({ url, ...options, body: options.body ? JSON.parse(options.body) : undefined });
    const next = responses.shift();
    if (next instanceof Error) throw next;
    return { ok: next.status >= 200 && next.status < 300, status: next.status, json: async () => next.body };
  };
  return { fetcher, calls };
}

test('add sends variant, quantity and current version, then shows the server cart', async () => {
  const { fetcher, calls } = fakeFetch([{ status: 200, body: { success: true, message: 'Đã thêm vào giỏ hàng.', data: cart() } }]);
  const store = cartStore({ fetcher, csrfToken: 'tok' });
  store.version = 2;

  assert.equal(await store.add(11, 1), true);
  assert.deepEqual(calls[0].body, { variant_id: 11, quantity: 1, version: 2 });
  assert.equal(calls[0].method, 'POST');
  assert.equal(calls[0].headers['X-CSRF-TOKEN'], 'tok');
  assert.equal(store.count, 2);
  assert.equal(store.version, 3);
  assert.equal(store.message, 'Đã thêm vào giỏ hàng.');
  assert.equal(store.isBusy(), false);
});

test('a stale version (409 CART_STALE) adopts the cart returned by the server', async () => {
  const { fetcher } = fakeFetch([{ status: 409, body: { success: false, code: 'CART_STALE', data: cart({ version: 9, count: 5 }) } }]);
  const store = cartStore({ fetcher });
  store.apply(cart());

  assert.equal(await store.update(store.items[0], 3), false);
  assert.equal(store.version, 9);
  assert.equal(store.count, 5);
  assert.match(store.error, /đã thay đổi/);
});

test('validation and stock errors show the first server message without changing the cart', async () => {
  const { fetcher } = fakeFetch([{ status: 409, body: { success: false, code: 'STOCK_UNAVAILABLE', errors: { quantity: ['Mỗi size chỉ có thể có tối đa 2 đôi trong giỏ.'] } } }]);
  const store = cartStore({ fetcher });
  store.apply(cart());

  assert.equal(await store.update(store.items[0], 3), false);
  assert.equal(store.items[0].quantity, 2);
  assert.equal(store.error, 'Mỗi size chỉ có thể có tối đa 2 đôi trong giỏ.');
});

test('network failure, expired session and throttling never report success', async () => {
  for (const [response, pattern] of [[new Error('offline'), /kết nối/], [{ status: 419, body: null }, /hết hạn/], [{ status: 401, body: null }, /hết hạn/], [{ status: 429, body: null }, /quá nhanh/]]) {
    const { fetcher } = fakeFetch([response]);
    const store = cartStore({ fetcher });
    store.apply(cart());
    assert.equal(await store.remove(store.items[0]), false);
    assert.equal(store.items.length, 1);
    assert.match(store.error, pattern);
  }
});

test('only one change is in flight at a time', async () => {
  let release;
  const fetcher = () => new Promise((resolve) => { release = () => resolve({ ok: true, status: 200, json: async () => ({ data: cart({ version: 4 }) }) }); });
  const store = cartStore({ fetcher });
  store.apply(cart());

  const first = store.update(store.items[0], 3);
  assert.equal(store.isBusy('item-7'), true);
  assert.equal(await store.add(12, 1), false);
  assert.match(store.error, /Đang xử lý/);
  release();
  assert.equal(await first, true);
  assert.equal(store.version, 4);
});

test('a missing item reloads the cart; adding without a size asks for one', async () => {
  const { fetcher, calls } = fakeFetch([{ status: 404, body: null }, { status: 200, body: { data: cart({ items: [], count: 0, subtotal: 0 }) } }]);
  const store = cartStore({ fetcher });
  store.apply(cart());

  assert.equal(await store.remove(store.items[0]), false);
  assert.equal(calls[1].method, 'GET');
  assert.equal(store.items.length, 0);
  assert.equal(await store.add(0), false);
  assert.match(store.error, /chọn size/);
});

test('formatVnd keeps integer VND', () => {
  assert.equal(formatVnd(1790000).replace(/\s/g, ' '), '1.790.000 ₫');
  assert.equal(formatVnd(1.5).replace(/\s/g, ' '), '0 ₫');
});

function clickHarness(dataset, addResult) {
  let handler;
  const root = { addEventListener: (type, fn) => { handler = fn; } };
  const events = [];
  const assigned = [];
  const target = { dispatchEvent: (event) => events.push(event.type), location: { assign: (url) => assigned.push(url) } };
  const button = { dataset, disabled: false, setAttribute() {}, removeAttribute() {}, closest: () => button };
  bindAddToCartButtons({ add: async () => addResult }, root, target);

  return { click: () => handler({ target: button, preventDefault() {} }), events, assigned };
}

test('"Mua ngay" goes to checkout only after the item was added', async () => {
  const ok = clickHarness({ variantId: '9', buyNow: '/checkout' }, true);
  await ok.click();
  assert.deepEqual(ok.assigned, ['/checkout']);
  assert.deepEqual(ok.events, []);

  const failed = clickHarness({ variantId: '9', buyNow: '/checkout' }, false);
  await failed.click();
  assert.deepEqual(failed.assigned, []);
  assert.deepEqual(failed.events, ['cart-open']);

  const foreign = clickHarness({ variantId: '9', buyNow: '//evil.test/x' }, true);
  await foreign.click();
  assert.deepEqual(foreign.assigned, []);
  assert.deepEqual(foreign.events, ['cart-open']);
});
