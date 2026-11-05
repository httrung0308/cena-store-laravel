// What-you-see editor for the article body (Tiptap). The body is still stored as the light markup that
// App\Support\ArticleFormatter renders (## heading, - item, 1. item, > quote, **bold**, *italic*): the editor
// loads it through renderArticle and writes it back through docToMarkup into the form's textarea, so the server
// keeps refusing HTML and stays the only place that decides what reaches the page.
// Tiptap is loaded on demand, only on the article form, so storefront pages do not download it.

const escapeHtml = (text) => text.replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char]);

function inline(text) {
    return escapeHtml(text.trim())
        .replace(/\*\*\*(?=\S)(.+?)(?<=\S)\*\*\*/gu, '<strong><em>$1</em></strong>')
        .replace(/\*\*(?=\S)(.+?)(?<=\S)\*\*/gu, '<strong>$1</strong>')
        .replace(/(?<![*\p{L}\p{N}_])\*(?=\S)([^*]+?)(?<=\S)\*(?![*\p{L}\p{N}_])/gu, '<em>$1</em>');
}

/** Same rules as ArticleFormatter::toHtml: markup → the HTML the editor (and the article page) shows. */
export function renderArticle(text) {
    let html = '';
    let block = null;
    let items = [];
    const flush = () => {
        if (block === 'p') html += `<p>${items.join('<br>')}</p>`;
        if (block === 'quote') html += `<blockquote><p>${items.join('<br>')}</p></blockquote>`;
        if (block === 'ul' || block === 'ol') html += `<${block}>${items.map((item) => `<li>${item}</li>`).join('')}</${block}>`;
        block = null;
        items = [];
    };
    for (const raw of text.trim().split(/\r\n|\r|\n/)) {
        const line = raw.trimEnd();
        if (line.trim() === '') { flush(); continue; }
        const heading = line.match(/^(#{2,3})\s+(.+)$/u);
        if (heading) { flush(); html += `<h${heading[1].length}>${inline(heading[2])}</h${heading[1].length}>`; continue; }
        let match;
        let type = 'p';
        let content = line;
        if ((match = line.match(/^\s*[-*]\s+(.+)$/u))) [type, content] = ['ul', match[1]];
        else if ((match = line.match(/^\s*\d{1,3}[.)]\s+(.+)$/u))) [type, content] = ['ol', match[1]];
        else if ((match = line.match(/^>\s?(.*)$/u))) [type, content] = ['quote', match[1]];
        if (block !== type) { flush(); block = type; }
        items.push(inline(content));
    }
    flush();

    return html;
}

/** Inline nodes → markup; spaces stay outside the markers so **bold** still parses. */
function inlineMarkup(nodes = [], lineBreak = '\n') {
    return nodes.map((node) => {
        if (node.type === 'hardBreak') return lineBreak;
        if (node.type !== 'text') return '';
        const marks = new Set((node.marks ?? []).map((mark) => mark.type));
        const [, lead, core, trail] = node.text.match(/^(\s*)([\s\S]*?)(\s*)$/);
        if (core === '') return node.text;
        const marker = (marks.has('bold') ? '**' : '') + (marks.has('italic') ? '*' : '');
        const closing = [...marker].reverse().join('');

        return lead + marker + core + closing + trail;
    }).join('');
}

/** Tiptap document JSON → the stored markup, one block per paragraph separated by a blank line. */
export function docToMarkup(doc) {
    const blocks = [];
    const listItems = (list, numbered) => {
        let index = 0;
        const lines = [];
        for (const item of list.content ?? []) {
            for (const child of item.content ?? []) {
                if (child.type === 'paragraph' || child.type === 'heading') {
                    index += 1;
                    lines.push(`${numbered ? `${index}.` : '-'} ${inlineMarkup(child.content, ' ').trim()}`);
                } else if (child.type === 'bulletList' || child.type === 'orderedList') {
                    lines.push(...listItems(child, child.type === 'orderedList'));
                }
            }
        }

        return lines.filter((line) => !/^(-|\d+\.)\s*$/.test(line));
    };
    for (const node of doc.content ?? []) {
        if (node.type === 'paragraph') {
            const text = inlineMarkup(node.content).trim();
            if (text) blocks.push(text);
        } else if (node.type === 'heading') {
            const text = inlineMarkup(node.content, ' ').trim();
            if (text) blocks.push(`${'#'.repeat(Math.min(3, Math.max(2, node.attrs?.level ?? 2)))} ${text}`);
        } else if (node.type === 'bulletList' || node.type === 'orderedList') {
            const lines = listItems(node, node.type === 'orderedList');
            if (lines.length) blocks.push(lines.join('\n'));
        } else if (node.type === 'blockquote') {
            const lines = (node.content ?? []).flatMap((child) => inlineMarkup(child.content).split('\n')).map((line) => line.trim()).filter(Boolean);
            if (lines.length) blocks.push(lines.map((line) => `> ${line}`).join('\n'));
        }
    }

    return blocks.join('\n\n');
}

const TOOLS = ['bold', 'italic', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote'];

export function articleEditor() {
    let editor = null;

    return {
        ready: false,
        active: Object.fromEntries(TOOLS.map((tool) => [tool, false])),
        canUndo: false,
        canRedo: false,
        words: 0,
        get minutes() {
            return Math.max(1, Math.ceil(this.words / 200));
        },
        async init() {
            const field = this.$refs.body;
            const [{ Editor }, { default: StarterKit }] = await Promise.all([import('@tiptap/core'), import('@tiptap/starter-kit')]);
            editor = new Editor({
                element: this.$refs.editor,
                extensions: [StarterKit.configure({
                    heading: { levels: [2, 3] },
                    code: false,
                    codeBlock: false,
                    strike: false,
                    horizontalRule: false,
                    underline: false,
                    link: false,
                })],
                content: renderArticle(field.value),
                editorProps: { attributes: { class: 'article-body min-h-[20rem] px-4 py-3 text-base leading-relaxed text-zinc-800 focus:outline-none', 'aria-label': 'Nội dung bài viết', 'aria-multiline': 'true', role: 'textbox' } },
                onUpdate: ({ editor: current }) => {
                    field.value = docToMarkup(current.getJSON());
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                },
                onTransaction: () => this.refresh(),
            });
            // The textarea keeps the value for the form; the server validates that it is not empty.
            field.required = false;
            this.ready = true;
            this.refresh();
        },
        refresh() {
            if (!editor) return;
            this.active = {
                bold: editor.isActive('bold'),
                italic: editor.isActive('italic'),
                h2: editor.isActive('heading', { level: 2 }),
                h3: editor.isActive('heading', { level: 3 }),
                bulletList: editor.isActive('bulletList'),
                orderedList: editor.isActive('orderedList'),
                blockquote: editor.isActive('blockquote'),
            };
            this.canUndo = editor.can().undo();
            this.canRedo = editor.can().redo();
            this.words = editor.state.doc.textBetween(0, editor.state.doc.content.size, ' ').split(/\s+/).filter(Boolean).length;
        },
        run(tool) {
            const chain = editor.chain().focus();
            ({
                bold: () => chain.toggleBold(),
                italic: () => chain.toggleItalic(),
                h2: () => chain.toggleHeading({ level: 2 }),
                h3: () => chain.toggleHeading({ level: 3 }),
                bulletList: () => chain.toggleBulletList(),
                orderedList: () => chain.toggleOrderedList(),
                blockquote: () => chain.toggleBlockquote(),
                undo: () => chain.undo(),
                redo: () => chain.redo(),
            })[tool]().run();
        },
        destroy() {
            editor?.destroy();
            editor = null;
        },
    };
}
