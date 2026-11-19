<script type="application/json" data-issue-office-options>@json(['divisions' => $field['division_options'], 'retainedNullDepartment' => $field['retained_null_department']])</script>
@push('after_scripts')
@basset(public_path('js/issue-office-selection.js'))
@endpush
