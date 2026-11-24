(function ($) {
    'use strict';
    if (!$ || !$.fn.dataTable) return;

    function updateControls(api) {
        const info = api.page.info();
        const wrapper = api.table().container();
        wrapper.querySelectorAll('.dataTables_paginate').forEach(element => { element.hidden = info.pages <= 1; });
        wrapper.querySelectorAll('.dataTables_length').forEach(element => { element.hidden = info.recordsTotal <= 10; });
    }

    function initialize() {
        document.querySelectorAll('[data-report-table]').forEach(table => {
            if ($.fn.dataTable.isDataTable(table)) return;
            const orderColumn = table.getAttribute('data-report-order-column');
            const options = {
                pageLength: 10,
                ordering: true,
                searching: true,
                order: orderColumn === null ? [] : [[Number(orderColumn), 'desc']],
                responsive: { details: { type: 'column', target: 'button.report-details-toggle' } },
                columnDefs: [{ targets: 0, className: 'control report-details-cell', orderable: false, searchable: false }],
                dom: "<'row align-items-center mb-2'<'col-md-6 report-table-title'><'col-md-6'f>>" +
                    "<'row'<'col-12 ec-report-table-scroll'tr>>" +
                    "<'row align-items-center mt-2'<'col-md-6'li><'col-md-6'p>>",
                language: { search: 'Search:', info: 'Showing _START_–_END_ of _TOTAL_ records', infoEmpty: '0 records',
                    emptyTable: table.getAttribute('data-report-empty') || 'No records for the selected date range and office.' },
                drawCallback: function () { updateControls(this.api()); }
            };
            const ajaxUrl = table.getAttribute('data-report-ajax');
            if (ajaxUrl) {
                options.serverSide = true;
                options.processing = true;
                options.ajax = { url: ajaxUrl, data: function (params) {
                    // The response endpoints retain their original data-column indexes.
                    (params.order || []).forEach(order => { order.column = Math.max(0, Number(order.column) - 1); });
                } };
                options.columns = [{ data: null, defaultContent: '<button type="button" class="btn btn-sm btn-outline-secondary report-details-toggle" aria-label="Show additional response details" aria-expanded="false">+</button>', orderable: false, searchable: false }];
                Array.from(table.querySelectorAll('thead th')).slice(1).forEach((header, index) => {
                    const textRenderer = $.fn.dataTable.render.text();
                    options.columns.push({ data: index, render: function (data, type) {
                        const escaped = textRenderer.display(data === null ? 'N/A' : data);
                        if (type !== 'display' || !header.getAttribute('data-report-rating')) return type === 'display' ? escaped : data;
                        const colors = { 'Very Satisfied': 'positive', 'Satisfied': 'agree', 'Dissatisfied': 'caution',
                            'Very Dissatisfied': 'negative', 'Strongly Agree': 'positive', 'Agree': 'agree',
                            'Neither': 'neutral', 'Disagree': 'caution', 'Strongly Disagree': 'negative' };
                        const color = Object.prototype.hasOwnProperty.call(colors, data) ? colors[data] : 'neutral';
                        return '<span class="badge ec-record-badge ec-rating-' + color + '">' + escaped + '</span>';
                    } });
                });
            }
            const api = $(table).DataTable(options);
            const wrapper = api.table().container();
            const scrollArea = wrapper.querySelector('.ec-report-table-scroll');
            if (scrollArea) {
                scrollArea.setAttribute('tabindex', '0');
                scrollArea.setAttribute('role', 'region');
                scrollArea.setAttribute('aria-label', table.getAttribute('aria-label') + ' table');
            }
            const heading = wrapper.querySelector('.report-table-title');
            const title = document.createElement('h5');
            title.className = 'mb-2 fw-bold';
            title.textContent = table.getAttribute('aria-label');
            heading.appendChild(title);
            $(table).on('responsive-display.dt', function (event, datatable, row, shown) {
                const button = row.node().querySelector('.report-details-toggle');
                if (button) {
                    button.setAttribute('aria-expanded', String(shown));
                    const label = button.getAttribute('aria-label') || 'Show additional details';
                    button.setAttribute('aria-label', label.replace(/^(Show|Hide)/, shown ? 'Hide' : 'Show'));
                    button.textContent = shown ? '−' : '+';
                }
            });
            updateControls(api);
        });
    }
    $(initialize);
})(window.jQuery);
