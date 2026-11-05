import assert from 'node:assert/strict';
import test from 'node:test';
import { prefixLines, renderArticle, wrapSelection } from '../../resources/js/components/article-editor.js';

test('bold and italic wrap the selection, or a placeholder when nothing is selected', () => {
    assert.deepEqual(wrapSelection('Giày êm', 5, 7, '**', '**', 'x'), { value: 'Giày **êm**', start: 7, end: 9 });
    assert.deepEqual(wrapSelection('Giày ', 5, 5, '*', '*', 'nghiêng'), { value: 'Giày *nghiêng*', start: 6, end: 13 });
});

test('line tools prefix every selected line, renumber lists and toggle off', () => {
    const text = 'Mở đầu\nĐo chân\nĐối chiếu';
    const listed = prefixLines(text, 8, 20, '1. ');
    assert.equal(listed.value, 'Mở đầu\n1. Đo chân\n2. Đối chiếu');
    assert.equal(prefixLines(listed.value, 8, listed.value.length, '1. ').value, text);
    assert.equal(prefixLines('## Cũ', 0, 0, '- ').value, '- Cũ', 'switching block type replaces the old marker');
    assert.equal(prefixLines('Tiêu đề', 3, 3, '## ').value, '## Tiêu đề');
});

test('preview mirrors the server formatter and escapes everything', () => {
    assert.equal(
        renderArticle('## Size\nĐo **chiều** *dài*\n\n- a\n- b\n\n1. c\n\n> d'),
        '<h2>Size</h2><p>Đo <strong>chiều</strong> <em>dài</em></p><ul><li>a</li><li>b</li></ul><ol><li>c</li></ol><blockquote><p>d</p></blockquote>',
    );
    assert.equal(renderArticle('**<img src=x onerror=alert(1)>** & 2*3'), '<p><strong>&lt;img src=x onerror=alert(1)&gt;</strong> &amp; 2*3</p>');
});
