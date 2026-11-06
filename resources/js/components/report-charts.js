// Back-office report charts. Long ranges are grouped by week (Monday first) so a quarter reads as ~13 bars
// instead of 90 spikes. Colors are read from CSS variables / tokens to respect light/dark themes.
// Every chart keeps one y-axis and has a table view in the page.
const WEEKLY_AFTER_DAYS = 31;

const money = new Intl.NumberFormat('vi-VN');
const vnd = (value) => `${money.format(value)} ₫`;

function getCssVar(name, fallback) {
    if (typeof window === 'undefined') return fallback;
    const val = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return val || fallback;
}

function getTheme() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark' ||
        (document.documentElement.getAttribute('data-theme') === 'system' && window.matchMedia?.('(prefers-color-scheme: dark)').matches);

    const colors = {
        cogs: isDark ? '#94a3b8' : '#64748b',
        expense: getCssVar('--accent-sub', '#be123c'),
        profit: getCssVar('--success', '#15803d'),
        count: isDark ? '#818cf8' : getCssVar('--accent', '#201464'),
    };

    const ink = {
        secondary: getCssVar('--text-muted', '#334155'),
        muted: isDark ? '#94a3b8' : '#64748b',
        grid: isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)',
        surface: getCssVar('--surface-card', isDark ? '#182235' : '#ffffff'),
        title: getCssVar('--text-main', isDark ? '#f8fafc' : '#020617'),
        border: getCssVar('--border', isDark ? '#29354d' : '#cbd5e1'),
    };

    return { colors, ink, isDark };
}

/** 1.250.000 → "1,25 tr", 3.400.000.000 → "3,4 tỷ": short labels for the money axis. */
export function compactVnd(value) {
    const abs = Math.abs(value);
    const short = (amount, unit) => `${(value / amount).toLocaleString('vi-VN', { maximumFractionDigits: 2 })} ${unit}`;
    if (abs >= 1e9) return short(1e9, 'tỷ');
    if (abs >= 1e6) return short(1e6, 'tr');
    if (abs >= 1e3) return short(1e3, 'k');
    return String(value);
}

const dayLabel = (day) => `${day.slice(8, 10)}/${day.slice(5, 7)}`;

/**
 * Sums daily rows into periods: one per day for short ranges, one per Monday-first week otherwise.
 * Partial first and last weeks keep their real dates in the label.
 */
export function buckets(rows, fields) {
    const weekly = rows.length > WEEKLY_AFTER_DAYS;
    const groups = [];
    rows.forEach((row) => {
        const weekday = (new Date(`${row.day}T00:00:00Z`).getUTCDay() + 6) % 7;
        if (!weekly || groups.length === 0 || weekday === 0) {
            groups.push({ first: row.day, last: row.day, ...Object.fromEntries(fields.map((field) => [field, 0])) });
        }
        const group = groups.at(-1);
        group.last = row.day;
        fields.forEach((field) => { group[field] += row[field]; });
    });

    return groups.map((group) => {
        const days = Math.round((Date.parse(group.last) - Date.parse(group.first)) / 86_400_000) + 1;
        const range = group.first === group.last ? dayLabel(group.first) : `${dayLabel(group.first)}–${dayLabel(group.last)}`;

        return { ...group, label: weekly && days < 7 ? `${range} (${days} ngày)` : range, short: dayLabel(group.first) };
    });
}

function createTooltip(theme) {
    return {
        backgroundColor: theme.ink.surface,
        titleColor: theme.ink.title,
        bodyColor: theme.ink.secondary,
        footerColor: theme.ink.title,
        borderColor: theme.ink.border,
        borderWidth: 1,
        padding: 12,
        boxPadding: 4,
        usePointStyle: true,
        titleFont: { family: 'Be Vietnam Pro', weight: '600' },
        bodyFont: { family: 'Be Vietnam Pro' },
        footerFont: { family: 'Be Vietnam Pro', weight: '600' },
    };
}

function createAxisX(theme) {
    return {
        grid: { display: false },
        border: { color: theme.ink.border },
        ticks: { color: theme.ink.muted, maxRotation: 0, autoSkipPadding: 12, font: { family: 'Be Vietnam Pro', size: 11 } }
    };
}

function createAxisY(theme, callback, extra = {}) {
    return {
        grid: { color: theme.ink.grid },
        border: { display: false },
        ticks: { color: theme.ink.muted, padding: 8, maxTicksLimit: 5, callback, font: { family: 'Be Vietnam Pro', size: 11 } },
        ...extra
    };
}

function financeChart(Chart, canvas, periods, theme) {
    const stack = (label, field, color, top) => ({
        label,
        data: periods.map((period) => period[field]),
        backgroundColor: color,
        borderColor: theme.ink.surface,
        borderWidth: { top: 2 },
        borderRadius: top ? { topLeft: 4, topRight: 4 } : 0,
        borderSkipped: 'bottom',
        maxBarThickness: 40,
        categoryPercentage: 0.75,
    });

    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels: periods.map((period) => period.short),
            datasets: [
                stack('Giá vốn', 'cogs', theme.colors.cogs, false),
                stack('Chi phí khác', 'expense', theme.colors.expense, false),
                stack('Lợi nhuận', 'profit', theme.colors.profit, true),
            ],
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...createTooltip(theme),
                    filter: (item) => item.parsed.y !== 0,
                    callbacks: {
                        title: (items) => periods[items[0].dataIndex].label,
                        label: (context) => ` ${context.dataset.label}: ${vnd(context.parsed.y)}`,
                        footer: (items) => `Doanh thu: ${vnd(periods[items[0].dataIndex].revenue)}`,
                    },
                },
            },
            scales: { x: { ...createAxisX(theme), stacked: true }, y: { ...createAxisY(theme, compactVnd), stacked: true } },
        },
    });
}

function marginChart(Chart, canvas, periods, theme) {
    const margin = periods.map((period) => (period.revenue > 0 ? Math.round((period.profit / period.revenue) * 1000) / 10 : null));
    const revenue = periods.reduce((sum, period) => sum + period.revenue, 0);
    const average = revenue > 0 ? Math.round((periods.reduce((sum, period) => sum + period.profit, 0) / revenue) * 1000) / 10 : null;
    const percent = (value) => `${value.toLocaleString('vi-VN', { maximumFractionDigits: 1 })}%`;

    return new Chart(canvas, {
        type: 'line',
        data: {
            labels: periods.map((period) => period.short),
            datasets: [
                {
                    label: 'Biên lợi nhuận',
                    data: margin,
                    borderColor: theme.colors.profit,
                    backgroundColor: theme.colors.profit,
                    borderWidth: 2,
                    tension: 0.3,
                    spanGaps: true,
                    pointRadius: periods.length > WEEKLY_AFTER_DAYS ? 0 : 4,
                    pointHoverRadius: 5,
                    pointBorderColor: theme.ink.surface,
                    pointBorderWidth: 2,
                },
                {
                    label: 'Trung bình cả kỳ',
                    data: periods.map(() => average),
                    borderColor: theme.ink.muted,
                    borderDash: [4, 4],
                    borderWidth: 1,
                    pointRadius: 0,
                    pointHoverRadius: 0,
                },
            ],
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...createTooltip(theme),
                    callbacks: {
                        title: (items) => periods[items[0].dataIndex].label,
                        label: (context) => (context.parsed.y === null ? ' Chưa có doanh thu' : ` ${context.dataset.label}: ${percent(context.parsed.y)}`),
                    },
                },
            },
            scales: { x: createAxisX(theme), y: createAxisY(theme, (value) => `${value}%`, { grace: '10%' }) },
        },
    });
}

/** One horizontal stacked bar per article: helpful then not helpful, counted in answers. */
function feedbackChart(Chart, canvas, articles, theme) {
    const stack = (label, field, color, last) => ({
        label,
        data: articles.map((article) => article[field]),
        backgroundColor: color,
        borderColor: theme.ink.surface,
        borderWidth: { right: 2 },
        borderRadius: last ? { topRight: 4, bottomRight: 4 } : 0,
        borderSkipped: 'left',
        maxBarThickness: 22,
    });
    const labelWidth = (chart) => Math.min(240, Math.round(chart.width * 0.4));
    const shorten = (title, chart) => {
        const fit = Math.max(8, Math.floor(labelWidth(chart) / 6.6));
        return title.length > fit ? `${title.slice(0, fit - 1)}…` : title;
    };

    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels: articles.map((article) => article.title),
            datasets: [stack('Hữu ích', 'helpful', theme.colors.profit, false), stack('Không hữu ích', 'unhelpful', theme.colors.expense, true)],
        },
        options: {
            indexAxis: 'y',
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false, axis: 'y' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...createTooltip(theme),
                    callbacks: {
                        title: (items) => articles[items[0].dataIndex].title,
                        label: (context) => ` ${context.dataset.label}: ${context.parsed.x} lượt`,
                        footer: (items) => {
                            const article = articles[items[0].dataIndex];
                            const total = article.helpful + article.unhelpful;
                            return total ? `${Math.round((article.helpful / total) * 100)}% thấy hữu ích` : '';
                        },
                    },
                },
            },
            scales: {
                x: { ...createAxisY(theme, (value) => (Number.isInteger(value) ? value : '')), stacked: true, beginAtZero: true },
                y: {
                    ...createAxisX(theme),
                    stacked: true,
                    grid: { display: false },
                    border: { display: false },
                    ticks: { ...createAxisX(theme).ticks, autoSkip: false, callback(value, index) { return shorten(articles[index].title, this.chart); } },
                    afterFit: (scale) => { scale.width = labelWidth(scale.chart); },
                },
            },
        },
    });
}

function sparkline(Chart, canvas, values, color) {
    return new Chart(canvas, {
        type: 'line',
        data: { labels: values.map((_, index) => index), datasets: [{ data: values, borderColor: color, backgroundColor: `${color}1a`, fill: 'origin', borderWidth: 2, tension: 0.35, pointRadius: 0 }] },
        options: {
            maintainAspectRatio: false,
            animation: false,
            events: [],
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            scales: { x: { display: false }, y: { display: false, grace: '5%' } },
        },
    });
}

export function reportCharts() {
    return {
        charts: [],
        observer: null,
        renderCharts() {
            const Chart = window.Chart;
            if (!Chart) return;
            this.destroy();

            const theme = getTheme();
            const data = JSON.parse(this.$refs.data.textContent);
            const moneyRows = buckets((data.daily ?? []).map((row) => ({ ...row, expense: row.revenue - row.cogs - row.profit })), ['revenue', 'cogs', 'expense', 'profit']);
            const operations = buckets(data.operations ?? [], ['orders', 'pairs']);
            const series = { ...Object.fromEntries(['revenue', 'cogs', 'expense', 'profit'].map((field) => [field, moneyRows])), orders: operations, pairs: operations };
            const sparkColor = { revenue: theme.colors.count, cogs: theme.colors.cogs, expense: theme.colors.expense, profit: theme.colors.profit, orders: theme.colors.count, pairs: theme.colors.count };

            if (this.$refs.money && moneyRows.length) this.charts.push(financeChart(Chart, this.$refs.money, moneyRows, theme));
            if (this.$refs.margin && moneyRows.length) this.charts.push(marginChart(Chart, this.$refs.margin, moneyRows, theme));
            if (this.$refs.feedback && data.feedback?.length) this.charts.push(feedbackChart(Chart, this.$refs.feedback, data.feedback, theme));
            this.$el.querySelectorAll('canvas[data-sparkline]').forEach((canvas) => {
                const field = canvas.dataset.sparkline;
                if (series[field]?.length > 1 && series[field].some((period) => period[field] !== 0)) this.charts.push(sparkline(Chart, canvas, series[field].map((period) => period[field]), sparkColor[field]));
            });
        },
        init() {
            this.renderCharts();
            if (typeof MutationObserver !== 'undefined') {
                this.observer = new MutationObserver(() => this.renderCharts());
                this.observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme', 'class'] });
            }
        },
        destroy() {
            this.charts.forEach((chart) => chart.destroy());
            this.charts = [];
            if (this.observer) {
                this.observer.disconnect();
                this.observer = null;
            }
        },
    };
}
