// Back-office report charts. Series colours come from the validated categorical order (blue, orange, aqua);
// every chart shares one y-axis per unit and ships with a legend plus a table view in the page.
const SERIES = ['#2a78d6', '#eb6834', '#1baf7a'];
const INK = { primary: '#18181b', secondary: '#52525b', muted: '#a1a1aa', grid: '#f4f4f5' };

const money = new Intl.NumberFormat('vi-VN');

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

function baseOptions(formatValue, formatTick) {
    return {
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        animation: { duration: 400 },
        plugins: {
            legend: {
                position: 'top',
                align: 'end',
                labels: { color: INK.secondary, boxWidth: 10, boxHeight: 10, useBorderRadius: true, borderRadius: 2, font: { family: 'Montserrat', size: 12 } },
            },
            tooltip: {
                backgroundColor: '#ffffff',
                titleColor: INK.primary,
                bodyColor: INK.secondary,
                borderColor: '#e4e4e7',
                borderWidth: 1,
                padding: 12,
                boxPadding: 4,
                usePointStyle: true,
                titleFont: { family: 'Montserrat', weight: '600' },
                bodyFont: { family: 'Montserrat' },
                callbacks: { label: (context) => ` ${context.dataset.label}: ${formatValue(context.parsed.y)}` },
            },
        },
        scales: {
            x: {
                grid: { display: false },
                border: { color: '#e4e4e7' },
                ticks: { color: INK.muted, maxRotation: 0, autoSkipPadding: 16, font: { family: 'Montserrat', size: 11 } },
            },
            y: {
                beginAtZero: true,
                grid: { color: INK.grid },
                border: { display: false },
                ticks: { color: INK.muted, padding: 8, maxTicksLimit: 6, callback: formatTick, font: { family: 'Montserrat', size: 11 } },
            },
        },
    };
}

function lineDataset(label, data, color, extra = {}) {
    return {
        type: 'line',
        label,
        data,
        borderColor: color,
        backgroundColor: color,
        borderWidth: 2,
        tension: 0.35,
        pointRadius: 0,
        pointHoverRadius: 5,
        pointHoverBorderWidth: 2,
        pointHoverBorderColor: '#ffffff',
        ...extra,
    };
}

function barDataset(label, data, color) {
    return {
        type: 'bar',
        label,
        data,
        backgroundColor: `${color}cc`,
        hoverBackgroundColor: color,
        borderRadius: { topLeft: 4, topRight: 4 },
        borderSkipped: 'bottom',
        maxBarThickness: 28,
        categoryPercentage: 0.8,
        barPercentage: 0.9,
    };
}

/** Soft vertical fade under the profit line, drawn once the chart knows its plot area. */
function fade(color) {
    return (context) => {
        const { ctx, chartArea } = context.chart;
        if (!chartArea) return `${color}22`;
        const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
        gradient.addColorStop(0, `${color}40`);
        gradient.addColorStop(1, `${color}00`);
        return gradient;
    };
}

export function reportCharts() {
    return {
        charts: [],
        init() {
            const data = JSON.parse(this.$refs.data.textContent);
            const Chart = window.Chart;
            if (!Chart) return;

            if (data.daily?.length && this.$refs.money) {
                const options = baseOptions((value) => `${money.format(value)} ₫`, compactVnd);
                this.charts.push(new Chart(this.$refs.money, {
                    data: {
                        labels: data.daily.map((row) => dayLabel(row.day)),
                        datasets: [
                            barDataset('Doanh thu', data.daily.map((row) => row.revenue), SERIES[0]),
                            lineDataset('Giá vốn', data.daily.map((row) => row.cogs), SERIES[1], { borderDash: [5, 4] }),
                            lineDataset('Lợi nhuận', data.daily.map((row) => row.profit), SERIES[2], { fill: 'origin', backgroundColor: fade(SERIES[2]) }),
                        ],
                    },
                    options,
                }));
            }

            if (data.operations?.length && this.$refs.operations) {
                const options = baseOptions((value) => money.format(value), (value) => (Number.isInteger(value) ? value : ''));
                this.charts.push(new Chart(this.$refs.operations, {
                    data: {
                        labels: data.operations.map((row) => dayLabel(row.day)),
                        datasets: [
                            barDataset('Đơn mới đặt', data.operations.map((row) => row.orders), SERIES[0]),
                            lineDataset('Đôi đã giao', data.operations.map((row) => row.pairs), SERIES[1]),
                        ],
                    },
                    options,
                }));
            }
        },
        destroy() {
            this.charts.forEach((chart) => chart.destroy());
        },
    };
}
