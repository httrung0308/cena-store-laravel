import assert from 'node:assert/strict';
import test from 'node:test';
import { coverProblem, slugify } from '../../resources/js/components/article-form.js';

test('slug preview matches the server slug for Vietnamese titles', () => {
    assert.equal(slugify("Air Force 1 '07: vì sao 40 năm vẫn bán chạy"), 'air-force-1-07-vi-sao-40-nam-van-ban-chay');
    assert.equal(slugify('Đôi giày chạy bộ — Pegasus 41!'), 'doi-giay-chay-bo-pegasus-41');
    assert.equal(slugify('   '), '');
    assert.ok(slugify('a '.repeat(300)).length <= 200);
});

test('covers are checked for type and the 5 MB limit before upload', () => {
    assert.equal(coverProblem({ type: 'image/png', size: 1024 }), null);
    assert.match(coverProblem({ type: 'image/gif', size: 1024 }), /JPEG, PNG hoặc WebP/);
    assert.match(coverProblem({ type: 'image/jpeg', size: 6.2 * 1024 * 1024 }), /tối đa 5 MB \(ảnh này 6,2 MB\)/);
    assert.equal(coverProblem({ type: 'image/webp', size: 5 * 1024 * 1024 }), null);
});
