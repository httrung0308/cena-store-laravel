// Light article editor for the admin form: a toolbar that inserts the same markup App\Support\ArticleFormatter
// renders (## headings, - lists, 1. lists, > quotes, **bold**, *italic*) into a plain textarea, plus a preview.
// The server stays the authority: it stores plain text and renders it; renderArticle only mirrors it for preview.

const escapeHtml = (text) => text.replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char]);

function inline(text) {
    return escapeHtml(text.trim())
        .replace(/\*\*(?=\S)(.+?)(?<=\S)\*\*/gu, '<strong>$1</strong>')
        .replace(/(?<![*\p{L}\p{N}_])\*(?=\S)([^*]+?)(?<=\S)\*(?![*\p{L}\p{N}_])/gu, '<em>$1</em>');
}

/** Same rules as ArticleFormatter::toHtml, for the preview only. */
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

/** Wraps the selection (or a placeholder) in markers, e.g. **bold**; returns the new value and selection. */
export function wrapSelection(value, start, end, before, after, placeholder) {
    const selected = value.slice(start, end) || placeholder;

    return {
        value: value.slice(0, start) + before + selected + after + value.slice(end),
        start: start + before.length,
        end: start + before.length + selected.length,
    };
}

/** Adds a line prefix ("## ", "- ", "1. ", "> ") to every selected line, or removes it when all already have it. */
export function prefixLines(value, start, end, prefix) {
    const lineStart = value.lastIndexOf('\n', start - 1) + 1;
    const lineEnd = value.indexOf('\n', end) === -1 ? value.length : value.indexOf('\n', end);
    const lines = value.slice(lineStart, lineEnd).split('\n');
    const numbered = prefix === '1. ';
    const marker = numbered ? /^\d{1,3}[.)]\s+/ : new RegExp(`^${prefix.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`);
    const allPrefixed = lines.every((line) => marker.test(line));
    const changed = lines.map((line, index) => {
        if (allPrefixed) return line.replace(marker, '');
        const bare = line.replace(/^(#{2,3}\s+|[-*]\s+|\d{1,3}[.)]\s+|>\s?)/, '');
        return (numbered ? `${index + 1}. ` : prefix) + bare;
    }).join('\n');

    return { value: value.slice(0, lineStart) + changed + value.slice(lineEnd), start: lineStart, end: lineStart + changed.length };
}

export function articleEditor() {
    return {
        previewing: false,
        preview: '',
        apply(result) {
            const area = this.$refs.body;
            area.value = result.value;
            area.focus();
            area.setSelectionRange(result.start, result.end);
            area.dispatchEvent(new Event('input', { bubbles: true }));
        },
        wrap(before, after, placeholder) {
            const area = this.$refs.body;
            this.apply(wrapSelection(area.value, area.selectionStart, area.selectionEnd, before, after, placeholder));
        },
        prefix(marker) {
            const area = this.$refs.body;
            this.apply(prefixLines(area.value, area.selectionStart, area.selectionEnd, marker));
        },
        shortcut(event) {
            if (!(event.ctrlKey || event.metaKey)) return;
            const key = event.key.toLowerCase();
            if (key === 'b') { event.preventDefault(); this.wrap('**', '**', 'chữ đậm'); }
            if (key === 'i') { event.preventDefault(); this.wrap('*', '*', 'chữ nghiêng'); }
        },
        togglePreview() {
            this.previewing = !this.previewing;
            if (this.previewing) this.preview = renderArticle(this.$refs.body.value) || '<p class="text-zinc-400">Chưa có nội dung.</p>';
        },
    };
}
