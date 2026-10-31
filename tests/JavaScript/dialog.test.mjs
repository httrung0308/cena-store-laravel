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
