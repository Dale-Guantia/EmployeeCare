(function () {
    'use strict';
    document.querySelectorAll('[data-division-chart-department]').forEach(select => {
        select.addEventListener('change', function () {
            const url = new URL(window.location.href);
            url.searchParams.set(select.getAttribute('data-division-chart-param') || 'chart_department_id', select.value);
            window.location.assign(url.toString());
        });
    });
    document.querySelectorAll('[data-independent-division-chart]').forEach(panel => {
        const data = JSON.parse(panel.querySelector('[data-division-chart-data]').textContent);
        const canvas = panel.querySelector('canvas');
        const empty = panel.querySelector('[data-chart-empty]');
        if (!data.labels.length || typeof Chart === 'undefined') {
            canvas.hidden = true;
            empty.hidden = false;
            empty.textContent = typeof Chart === 'undefined' ? 'Chart unavailable.' : 'No divisions in this department.';
            return;
        }
        new Chart(canvas, {
            type: 'bar',
            data: { labels: data.labels, datasets: [{ label: 'Tickets',
                data: data.values, backgroundColor: data.colors, borderRadius: 6, maxBarThickness: 22 }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, animation: false,
                interaction: { mode: 'index', axis: 'y', intersect: false },
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#17233b',
                    titleColor: '#fff', bodyColor: '#e7edf8', padding: 12, cornerRadius: 8, callbacks: {
                    title: items => {
                        if (!items.length) return [];
                        const characters = Array.from(data.labels[items[0].dataIndex]);
                        const lines = [];
                        for (let start = 0; start < characters.length; start += 40) lines.push(characters.slice(start, start + 40).join(''));
                        return lines;
                    },
                    label: item => 'Tickets: ' + data.values[item.dataIndex]
                } } },
                scales: {
                    x: { beginAtZero: true, border: { display: false }, grid: { color: '#edf1f7' },
                        title: { display: true, text: 'Tickets', color: '#64748b', font: { size: 11 } },
                        ticks: { precision: 0, color: '#64748b', font: { size: 11 } } },
                    y: { border: { display: false }, grid: { display: false }, ticks: { color: '#53647d', font: { size: 11 }, autoSkip: false, callback: function (value) {
                        const name = Array.from(this.getLabelForValue(value));
                        return name.length > 28 ? name.slice(0, 27).join('') + '…' : name.join('');
                    } } }
                }
            }
        });
    });
})();
