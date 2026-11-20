import test from 'node:test';
import assert from 'node:assert/strict';
import { toastsController } from '../../resources/js/components/toasts.js';

function clock() {
  let t = 0, next = 0;
  const jobs = new Map();
  return { now: () => t, schedule: (fn, ms) => { const id = ++next; jobs.set(id, { fn, at: t + ms }); return id; }, cancel: id => jobs.delete(id), tick(ms) { t += ms; for (const [id, job] of [...jobs]) if (job.at <= t) { jobs.delete(id); job.fn(); } } };
}
test('queue, pause, resume and cleanup are isolated', () => {
  const c = clock();
  const first = toastsController({ ...c, timeout: 100 });
  const second = toastsController({ ...c, timeout: 100 });
  const id = first.notify({ type: 'success', message: 'OK' });
  second.notify({ type: 'error', message: 'Second' });
  c.tick(40); first.pause(id); c.tick(60);
  assert.equal(first.items.length, 1); assert.equal(second.items.length, 0);
  first.resume(id); c.tick(60); assert.equal(first.items.length, 0);
  first.destroy(); second.destroy();
});
test('unknown type is neutral; unsafe input never becomes a CSS class or HTML', () => {
  const t = toastsController({ timeout: 0 });
  t.notify({ message: '<img src=x onerror=alert(1)>', type: 'bg-[url(javascript:evil)]', request_id: 'R-1' });
  assert.equal(t.items[0].type, 'info');
  assert.equal(t.items[0].message, '<img src=x onerror=alert(1)>');
  assert.equal(t.items[0].request_id, 'R-1');
  assert.equal(t.notify({ message: '' }), null);
});
test('default timeouts differentiate errors from success', () => {
  const c = clock();
  const controller = toastsController({ ...c });
  controller.notify({ type: 'success', message: 'Thành công' });
  controller.notify({ type: 'error', message: 'Lỗi quan trọng' });
  c.tick(5000);
  // Success should auto-dismiss after 5000ms, while error remains visible
  assert.equal(controller.items.length, 1);
  assert.equal(controller.items[0].type, 'error');
  controller.destroy();
});
