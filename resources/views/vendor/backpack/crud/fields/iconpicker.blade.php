@php
    $field['value'] = old_empty_or_null($field['name'], '') ?? $field['value'] ?? $field['default'] ?? '';
@endphp

@include('crud::fields.inc.wrapper_start')

    <label>{!! $field['label'] !!}</label>

    <div class="input-group">
        <input
            type="text"
            name="{{ $field['name'] }}"
            value="{{ $field['value'] }}"
            class="form-control"
            id="iconpicker-input-{{ $field['name'] }}"
            readonly
        >

        <div class="input-group-append">
            <button
                type="button"
                class="btn btn-secondary"
                id="iconpicker-btn-{{ $field['name'] }}"
            ></button>
        </div>
    </div>

    @if (!empty($field['hint']))
        <small class="form-text text-muted">{!! $field['hint'] !!}</small>
    @endif

@include('crud::fields.inc.wrapper_end')

@push('crud_fields_styles')
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.3.1/css/all.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-iconpicker/1.10.0/css/bootstrap-iconpicker.min.css">
@endpush

@push('crud_fields_scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-iconpicker/1.10.0/js/bootstrap-iconpicker.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-iconpicker/1.10.0/js/iconset/fontawesome5.min.js"></script>

    <script>
        jQuery(function ($) {
            var $button = $('#iconpicker-btn-{{ $field['name'] }}');
            var $input  = $('#iconpicker-input-{{ $field['name'] }}');

            $button.iconpicker({
                iconset: 'fontawesome5',
                icon: $input.val(),
                search: true,
                cols: 5,
                rows: 5,
                selectedClass: 'btn-success',
                unselectedClass: 'btn-outline-secondary'
            });

            function setIconValue(icon) {
                if (!icon) return;

                // if plugin returns only fa-key, convert to fas fa-key
                if (icon.indexOf('fa ') === -1 && icon.indexOf('fas ') !== 0 && icon.indexOf('far ') !== 0 && icon.indexOf('fab ') !== 0) {
                    icon = 'fas ' + icon;
                }

                $input.val(icon);
            }

            // works in most cases
            $button.on('change', function(e) {
                setIconValue(e.icon);
            });

            // extra fallback
            $button.on('iconpickerSelected', function(e) {
                if (e.iconpickerValue) {
                    setIconValue(e.iconpickerValue);
                }
            });

            // initial value sync
            if ($input.val()) {
                setIconValue($input.val());
            }
        });
    </script>
@endpush
