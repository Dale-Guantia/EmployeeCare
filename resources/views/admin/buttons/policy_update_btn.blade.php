{{-- Per-row update button --}}
@if($crud->hasAccess('list'))
<a href="{{ route('hr.policy.update.form', $entry->id) }}"
   class="btn btn-sm btn-warning">
    <i class="la la-sync"></i> Update
</a>
@endif
