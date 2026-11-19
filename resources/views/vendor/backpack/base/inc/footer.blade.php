@if(backpack_auth()->check())
@basset('https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css')
@basset('https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js')
<script>
// Backpack-PRO-free searchable dropdowns via Tom Select (MIT licensed).
window.hrfInitTomSelect = function (selectorOrEl, options) {
    options = Object.assign({}, options || {});
    var emptyOptionText = options.emptyOptionText;
    delete options.emptyOptionText;

    var els = typeof selectorOrEl === 'string'
        ? document.querySelectorAll(selectorOrEl)
        : [selectorOrEl];

    els.forEach(function (el) {
        if (!el || el.tomselect) return;

        if (emptyOptionText) {
            var blank = el.querySelector('option[value=""]');
            if (blank) { blank.textContent = emptyOptionText; }
        }

        new TomSelect(el, Object.assign({
            create: false,
            allowEmptyOption: true,
            sortField: { field: 'text', direction: 'asc' },
        }, options));
    });
};

window.hrfRebindTomSelect = function (selectEl, options) {
    if (!selectEl) return;
    if (selectEl.tomselect) {
        selectEl.tomselect.destroy();
    }
    window.hrfInitTomSelect(selectEl, options || {});
};
</script>
<script src="{{ asset('js/notifications.js') }}?v={{ filemtime(public_path('js/notifications.js')) }}" defer></script>
@endif

@include(backpack_view('inc.hr-float-widget'))
