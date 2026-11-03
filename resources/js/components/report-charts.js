// Back-office report charts. Long ranges are grouped by week (Monday first) so a quarter reads as ~13 bars
// instead of 90 spikes. Colours: neutral gray for COGS, the validated categorical orange/aqua for other expenses
// and profit, blue for counts. Every chart keeps one y-axis and has a table view in the page.
const COLORS = { cogs: '#b4b2a9', expense: '#eb6834', profit: '#1baf7a', count: '#2a78d6' };
const INK = { secondary: '#52525b', muted: '#a1a1aa', grid: '#f4f4f5', surface: '#ffffff' };
const WEEKLY_AFTER_DAYS = 31;

const money = new Intl.NumberFormat('vi-VN');
const vnd = (value) => `${money.format(value)} ₫`;

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

const tooltip = {
    backgroundColor: INK.surface,
    titleColor: '#18181b',
    bodyColor: INK.secondary,
    footerColor: '#18181b',
    borderColor: '#e4e4e7',
    borderWidth: 1,
    padding: 12,
    boxPadding: 4,
    usePointStyle: true,
    titleFont: { family: 'Montserrat', weight: '600' },
    bodyFont: { family: 'Montserrat' },
    footerFont: { family: 'Montserrat', weight: '600' },
};

const axisX = { grid: { display: false }, border: { color: '#e4e4e7' }, ticks: { color: INK.muted, maxRotation: 0, autoSkipPadding: 12, font: { family: 'Montserrat', size: 11 } } };
const axisY = (callback, extra = {}) => ({ grid: { color: INK.grid }, border: { display: false }, ticks: { color: INK.muted, padding: 8, maxTicksLimit: 5, callback, font: { family: 'Montserrat', size: 11 } }, ...extra });

function financeChart(Chart, canvas, periods) {
    const stack = (label, field, color, top) => ({
        label,
        data: periods.map((period) => period[field]),
        backgroundColor: color,
        borderColor: INK.surface,
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
                stack('Giá vốn', 'cogs', COLORS.cogs, false),
                stack('Chi phí khác', 'expense', COLORS.expense, false),
                stack('Lợi nhuận', 'profit', COLORS.profit, true),
            ],
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...tooltip,
                    filter: (item) => item.parsed.y !== 0,
                    callbacks: {
                        title: (items) => periods[items[0].dataIndex].label,
                        label: (context) => ` ${context.dataset.label}: ${vnd(context.parsed.y)}`,
                        footer: (items) => `Doanh thu: ${vnd(periods[items[0].dataIndex].revenue)}`,
                    },
                },
            },
            scales: { x: { ...axisX, stacked: true }, y: { ...axisY(compactVnd), stacked: true } },
        },
    });
}

function marginChart(Chart, canvas, periods) {
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
                    borderColor: COLORS.profit,
                    backgroundColor: COLORS.profit,
                    borderWidth: 2,
                    tension: 0.3,
                    spanGaps: true,
                    pointRadius: periods.length > WEEKLY_AFTER_DAYS ? 0 : 4,
                    pointHoverRadius: 5,
                    pointBorderColor: INK.surface,
                    pointBorderWidth: 2,
                },
                {
                    label: 'Trung bình cả kỳ',
                    data: periods.map(() => average),
                    borderColor: INK.muted,
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
                    ...tooltip,
                    callbacks: {
                        title: (items) => periods[items[0].dataIndex].label,
                        label: (context) => (context.parsed.y === null ? ' Chưa có doanh thu' : ` ${context.dataset.label}: ${percent(context.parsed.y)}`),
                    },
                },
            },
            scales: { x: axisX, y: axisY((value) => `${value}%`, { grace: '10%' }) },
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
        init() {
            const Chart = window.Chart;
            if (!Chart) return;
            const data = JSON.parse(this.$refs.data.textContent);
            const money = buckets((data.daily ?? []).map((row) => ({ ...row, expense: row.revenue - row.cogs - row.profit })), ['revenue', 'cogs', 'expense', 'profit']);
            const operations = buckets(data.operations ?? [], ['orders', 'pairs']);
            const series = { ...Object.fromEntries(['revenue', 'cogs', 'expense', 'profit'].map((field) => [field, money])), orders: operations, pairs: operations };
            const sparkColor = { revenue: COLORS.count, cogs: COLORS.cogs, expense: COLORS.expense, profit: COLORS.profit, orders: COLORS.count, pairs: COLORS.count };

            if (this.$refs.money && money.length) this.charts.push(financeChart(Chart, this.$refs.money, money));
            if (this.$refs.margin && money.length) this.charts.push(marginChart(Chart, this.$refs.margin, money));
            this.$el.querySelectorAll('canvas[data-sparkline]').forEach((canvas) => {
                const field = canvas.dataset.sparkline;
                if (series[field]?.length > 1 && series[field].some((period) => period[field] !== 0)) this.charts.push(sparkline(Chart, canvas, series[field].map((period) => period[field]), sparkColor[field]));
            });
        },
        destroy() {
            this.charts.forEach((chart) => chart.destroy());
        },
    };
}
