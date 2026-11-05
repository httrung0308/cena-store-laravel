import assert from 'node:assert/strict';
import test from 'node:test';
import { docToMarkup, renderArticle } from '../../resources/js/components/article-editor.js';

const text = (value, ...marks) => ({ type: 'text', text: value, ...(marks.length ? { marks: marks.map((type) => ({ type })) } : {}) });
const paragraph = (...content) => ({ type: 'paragraph', content });
const item = (...content) => ({ type: 'listItem', content: [paragraph(...content)] });

test('the editor document is saved as the markup the server renders', () => {
    const doc = {
        type: 'doc',
        content: [
            { type: 'heading', attrs: { level: 2 }, content: [text('Ba bước chọn size')] },
            paragraph(text('Đo chân vào '), text('buổi chiều ', 'bold'), text('lúc chân '), text('nở nhất', 'italic'), text('.'), { type: 'hardBreak' }, text('Cộng 0,5 cm.')),
            paragraph(),
            { type: 'bulletList', content: [item(text('Mang tất')), item(text('Chân bè: '), text('tăng nửa size', 'bold', 'italic'))] },
            { type: 'orderedList', content: [item(text('Đo')), item(text('Đối chiếu'))] },
            { type: 'heading', attrs: { level: 3 }, content: [text('Lưu ý')] },
            { type: 'blockquote', content: [paragraph(text('Đổi size trong 7 ngày'))] },
        ],
    };

    assert.equal(docToMarkup(doc), [
        '## Ba bước chọn size',
        'Đo chân vào **buổi chiều** lúc chân *nở nhất*.\nCộng 0,5 cm.',
        '- Mang tất\n- Chân bè: ***tăng nửa size***',
        '1. Đo\n2. Đối chiếu',
        '### Lưu ý',
        '> Đổi size trong 7 ngày',
    ].join('\n\n'));
});

test('saved markup loads back into the same structure', () => {
    const markup = '## Size\n\nĐo **chiều** *dài* và ***rộng***\n\n- a\n- b\n\n1. c\n\n> d';
    assert.equal(
        renderArticle(markup),
        '<h2>Size</h2><p>Đo <strong>chiều</strong> <em>dài</em> và <strong><em>rộng</em></strong></p><ul><li>a</li><li>b</li></ul><ol><li>c</li></ol><blockquote><p>d</p></blockquote>',
    );
});

test('loading escapes everything, so stored text can never inject tags into the editor', () => {
    assert.equal(renderArticle('**<img src=x onerror=alert(1)>** & 2*3'), '<p><strong>&lt;img src=x onerror=alert(1)&gt;</strong> &amp; 2*3</p>');
    assert.equal(docToMarkup({ type: 'doc', content: [paragraph(), { type: 'bulletList', content: [item()] }] }), '', 'empty blocks are dropped');
});
