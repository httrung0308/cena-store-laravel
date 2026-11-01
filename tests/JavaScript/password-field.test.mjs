import assert from 'node:assert/strict';
import test from 'node:test';

import { passwordField } from '../../resources/js/components/password-field.js';

test('password field starts masked and toggles its type and accessible label', () => {
    const field = passwordField();

    assert.equal(field.shown, false);
    assert.equal(field.inputType, 'password');
    assert.equal(field.toggleLabel, 'Hiện mật khẩu');

    field.toggle();
    assert.equal(field.shown, true);
    assert.equal(field.inputType, 'text');
    assert.equal(field.toggleLabel, 'Ẩn mật khẩu');

    field.toggle();
    assert.equal(field.shown, false);
    assert.equal(field.inputType, 'password');
    assert.equal(field.toggleLabel, 'Hiện mật khẩu');
});

test('each field instance has isolated visibility state', () => {
    const currentPassword = passwordField();
    const newPassword = passwordField();

    assert.notStrictEqual(currentPassword, newPassword);

    currentPassword.toggle();
    assert.equal(currentPassword.inputType, 'text');
    assert.equal(newPassword.inputType, 'password');
    assert.equal(newPassword.toggleLabel, 'Hiện mật khẩu');

    newPassword.toggle();
    currentPassword.toggle();
    assert.equal(currentPassword.inputType, 'password');
    assert.equal(newPassword.inputType, 'text');
    assert.equal(newPassword.toggleLabel, 'Ẩn mật khẩu');
});
