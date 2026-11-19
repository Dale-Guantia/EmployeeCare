(function () {
    'use strict';
    document.querySelectorAll('[data-survey-chart]').forEach(panel => {
        const canvas = panel.querySelector('canvas');
        const empty = panel.querySelector('[data-chart-empty]');
        const data = JSON.parse(panel.querySelector('[data-survey-chart-data]').textContent);
        if (!data.counts.some(count => Number(count) > 0) || typeof Chart === 'undefined') {
            canvas.hidden = true;
            empty.hidden = false;
            empty.textContent = typeof Chart === 'undefined' ? 'Chart unavailable. Summary counts are shown above.' : 'No data to display.';
            return;
        }
        new Chart(canvas, {
            type: 'bar',
            data: { labels: data.labels, datasets: [{ label: data.unit || 'Responses', data: data.counts,
                backgroundColor: data.colors || '#375ca6', borderRadius: 6, maxBarThickness: 24 }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, animation: false,
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#17233b', padding: 12,
                    cornerRadius: 8, callbacks: {
                        title: items => {
                            if (!items.length) return [];
                            const characters = Array.from(data.labels[items[0].dataIndex]);
                            const lines = [];
                            for (let i = 0; i < characters.length; i += 40) lines.push(characters.slice(i, i + 40).join(''));
                            return lines;
                        },
                        label: context => (data.unit || 'Responses') + ': ' + data.counts[context.dataIndex]
                    } } },
                scales: { x: { beginAtZero: true, border: { display: false }, grid: { color: '#edf1f7' },
                    ticks: { precision: 0, color: '#64748b', font: { size: 11 } } },
                    y: { border: { display: false }, grid: { display: false }, ticks: { color: '#53647d', autoSkip: false,
                        font: { size: 11 }, callback: function (value) {
                            const name = Array.from(this.getLabelForValue(value));
                            return name.length > 28 ? name.slice(0, 27).join('') + '…' : name.join('');
                        } } } }
            }
        });
    });
})();
