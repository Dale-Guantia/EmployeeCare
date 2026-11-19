(function () {
    'use strict';
    const payload = document.querySelector('[data-dashboard-charts], [data-report-charts]');
    if (!payload) return;
    const data = JSON.parse(payload.textContent);
    const tooltip = { backgroundColor: '#17233b', titleColor: '#fff', bodyColor: '#e7edf8',
        padding: 12, cornerRadius: 8, displayColors: true };

    function dateLabel(label, full) {
        const match = /^(\d{4})-(\d{2})(?:-(\d{2}))?$/.exec(label);
        if (!match) return label;
        return new Intl.DateTimeFormat('en-US', { timeZone: 'UTC', month: full ? 'long' : 'short',
            ...(match[3] ? { day: 'numeric' } : {}), ...(full || !match[3] ? { year: 'numeric' } : {})
        }).format(new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3] || 1))));
    }

    function draw(id, counts, config) {
        const canvas = document.getElementById(id);
        if (!canvas) return;
        const empty = canvas.closest('[data-analytics-chart-panel]').querySelector('[data-analytics-chart-empty]');
        const available = typeof Chart !== 'undefined';
        canvas.hidden = !available || !counts.some(value => Number(value) > 0);
        empty.hidden = !canvas.hidden;
        if (!available) empty.textContent = 'Chart unavailable.';
        if (!canvas.hidden) new Chart(canvas, config);
    }

    if (data.status) {
        const statusTotal = data.status.counts.reduce((sum, value) => sum + Number(value), 0);
        draw('statusChart', data.status.counts, {
            type: 'doughnut',
            data: { labels: data.status.labels, datasets: [{ data: data.status.counts,
                backgroundColor: data.status.colors, borderColor: '#fff', borderWidth: 3, borderRadius: 4, spacing: 2 }] },
            options: { responsive: true, maintainAspectRatio: false, animation: false, cutout: '68%',
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#53647d', usePointStyle: true,
                        pointStyle: 'circle', boxWidth: 8, boxHeight: 8, padding: 14, font: { size: 11 } } },
                    tooltip: { ...tooltip, callbacks: { label: context => context.label + ': ' + context.parsed + ' tickets ('
                        + (statusTotal ? Math.round(Number(context.parsed) / statusTotal * 1000) / 10 : 0) + '%)' } }
                }
            }
        });
    }

    draw(data.volume.canvasId || 'dashVolumeTrendChart', data.volume.counts, {
        type: 'line',
        data: { labels: data.volume.labels, datasets: [{ label: 'Tickets created', data: data.volume.counts,
            borderColor: '#375ca6', backgroundColor: 'rgba(55, 92, 166, .09)', fill: true,
            borderWidth: 2.5, tension: 0, pointRadius: data.volume.counts.length <= 15 ? 2 : 0,
            pointHoverRadius: 5, pointHitRadius: 12, pointBackgroundColor: '#fff', pointBorderColor: '#375ca6', pointBorderWidth: 2 }] },
        options: { responsive: true, maintainAspectRatio: false, animation: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false }, tooltip: { ...tooltip, callbacks: {
                title: items => items.length ? dateLabel(items[0].label, true) : '',
                label: context => 'Tickets created: ' + context.parsed.y
            } } },
            scales: {
                x: { grid: { display: false }, border: { display: false }, ticks: { color: '#64748b',
                    maxRotation: 0, maxTicksLimit: 5, font: { size: 11 }, callback: function (value) {
                        return dateLabel(this.getLabelForValue(value), false);
                    } } },
                y: { beginAtZero: true, border: { display: false }, grid: { color: '#edf1f7' },
                    title: { display: true, text: 'Tickets', color: '#64748b', font: { size: 11 } },
                    ticks: { precision: 0, maxTicksLimit: 5, color: '#64748b', font: { size: 11 } } }
            }
        }
    });

})();
