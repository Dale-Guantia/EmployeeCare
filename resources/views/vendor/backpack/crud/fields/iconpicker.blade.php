@php
    $field['value'] = old_empty_or_null($field['name'], '') ?? $field['value'] ?? $field['default'] ?? '';
    $iconChoices = json_decode(file_get_contents(resource_path('data/fontawesome-icons.json')), true, 512, JSON_THROW_ON_ERROR);
    $iconInputId = 'iconpicker-' . $field['name'];
@endphp

@include('crud::fields.inc.wrapper_start')
    <label class="form-label" for="{{ $iconInputId }}">{!! $field['label'] !!}</label>
    <div data-icon-picker>
      <div class="input-group">
        <input type="text" name="{{ $field['name'] }}" value="{{ $field['value'] }}"
               class="form-control" id="{{ $iconInputId }}"
               placeholder="Choose an icon below, or enter its class" autocomplete="off">
        <span class="input-group-text" aria-hidden="true"><i data-icon-preview></i></span>
      </div>
      <details class="icon-library mt-2" data-icon-library>
        <summary class="btn btn-outline-primary btn-sm">Browse icon library</summary>
        <div class="border rounded p-3 mt-2 bg-white">
          <label class="form-label" for="{{ $iconInputId }}-search">Search icons</label>
          <input id="{{ $iconInputId }}-search" type="search" class="form-control mb-2" data-icon-search placeholder="Search by name, e.g. user, file, balance">
          <div class="icon-library-grid" data-icon-grid role="group" aria-label="Available icons"></div>
          <div class="d-flex align-items-center justify-content-between gap-2 mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-icon-previous>Previous</button>
            <small data-icon-page aria-live="polite"></small>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-icon-next>Next</button>
          </div>
        </div>
      </details>
      <script type="application/json" data-icon-options>@json($iconChoices)</script>
    </div>
    @if (!empty($field['hint']))
        <small class="form-text text-muted">{!! $field['hint'] !!}</small>
    @endif
@include('crud::fields.inc.wrapper_end')

@push('crud_fields_styles')
    @basset(public_path('public-assets/css/fontawesome.min.css'))
@endpush

@push('crud_fields_scripts')
    @basset(public_path('js/icon-picker.js'))
@endpush
