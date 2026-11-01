import assert from 'node:assert/strict';
import test from 'node:test';
import { dialogController } from '../../resources/js/components/dialog.js';

function node(name) { return { name, focused: 0, tabIndex: 0, focus() { this.focused += 1; } }; }
function fixture() {
    const body = { style: { overflow: 'scroll' } }, trigger = node('trigger');
    const a = node('first'), b = node('last');
    const doc = { body, activeElement: a };
    const panel = { focus() {}, querySelectorAll() { return [a, b]; } };
    const dialog = dialogController(); dialog.attach({ panel, trigger, document: doc });
    return { dialog, body, trigger, a, b, doc };
}
test('trap Tab, ShiftTab, Escape and return focus to original trigger', () => {
    const { dialog, body, trigger, a, b, doc } = fixture();
    dialog.openDialog(); assert.equal(dialog.opened, true); assert.equal(body.style.overflow, 'hidden');
    let prevented = 0;
    doc.activeElement = b; dialog.onKeydown({ key: 'Tab', shiftKey: false, preventDefault() { prevented++; } });
    assert.equal(a.focused > 0, true);
    doc.activeElement = a; dialog.onKeydown({ key: 'Tab', shiftKey: true, preventDefault() { prevented++; } });
    assert.equal(b.focused, 1);
    dialog.onKeydown({ key: 'Escape', preventDefault() { prevented++; } });
    assert.equal(dialog.opened, false); assert.equal(body.style.overflow, 'scroll');
    assert.equal(trigger.focused, 1); assert.equal(prevented, 3);
});
test('two dialogs retain shared scroll lock and busy prevents close', () => {
    const x = fixture(), y = fixture(); y.dialog.attach({ panel: y.dialog.panel, trigger: y.trigger, document: y.doc, body: x.body });
    x.dialog.openDialog(); y.dialog.openDialog();
    y.dialog.busy = true; y.dialog.closeDialog(); assert.equal(y.dialog.opened, true);
    x.dialog.destroy(); assert.equal(x.body.style.overflow, 'hidden');
    y.dialog.busy = false; y.dialog.destroy(); assert.equal(x.body.style.overflow, 'scroll');
});

test('initial focus runs only after the injected DOM visibility flush', () => {
    const { dialog, trigger, a, body } = fixture();
    const scheduled = [];
    dialog.attach({ panel: dialog.panel, trigger, document: dialog.doc, body, scheduleFocus: cb => scheduled.push(cb) });
    dialog.openDialog();
    assert.equal(dialog.opened, true);
    assert.equal(a.focused, 0, 'must not focus while the x-show overlay is hidden');
    assert.equal(scheduled.length, 1);
    scheduled.shift()(); // Harness equivalent of Alpine $nextTick after x-show becomes visible.
    assert.equal(a.focused, 1);
    dialog.closeDialog();
    assert.equal(trigger.focused, 1);
    assert.equal(body.style.overflow, 'scroll');
});

test('close or destroy invalidates pending focus callbacks', () => {
    const { dialog, trigger, a, body } = fixture();
    const callbacks = [];
    dialog.attach({ panel: dialog.panel, trigger, document: dialog.doc, body, scheduleFocus: cb => callbacks.push(cb) });
    dialog.openDialog();
    dialog.closeDialog();
    callbacks.shift()();
    assert.equal(a.focused, 0);
    assert.equal(trigger.focused, 1);
    dialog.openDialog();
    dialog.destroy();
    callbacks.shift()();
    assert.equal(a.focused, 0);
    assert.equal(body.style.overflow, 'scroll');
});

test('older pending callbacks cannot steal focus from newer dialog', () => {
    const left = fixture(), right = fixture(), pending = [];
    left.dialog.attach({ panel: left.dialog.panel, trigger: left.trigger, document: left.doc, body: left.body, scheduleFocus: cb => pending.push(cb) });
    right.dialog.attach({ panel: right.dialog.panel, trigger: right.trigger, document: right.doc, body: left.body, scheduleFocus: cb => pending.push(cb) });
    left.dialog.openDialog();
    right.dialog.openDialog();
    pending[0]();
    assert.equal(left.a.focused, 0);
    pending[1]();
    assert.equal(right.a.focused, 1);
    right.dialog.closeDialog();
    assert.equal(left.body.style.overflow, 'hidden');
    left.dialog.destroy();
    assert.equal(left.body.style.overflow, 'scroll');
});

test('backdrop and Escape cannot close busy dialog', () => {
    const { dialog } = fixture();
    dialog.openDialog();
    const backdrop = {};
    dialog.busy = true;
    dialog.onBackdrop({ target: backdrop, currentTarget: backdrop });
    dialog.onKeydown({ key: 'Escape', preventDefault() { assert.fail('busy modal must not prevent Escape'); } });
    assert.equal(dialog.opened, true);
    dialog.busy = false;
    dialog.onBackdrop({ target: backdrop, currentTarget: backdrop });
    assert.equal(dialog.opened, false);
});
