{{-- New upload button shown in the list header --}}
@if($crud->hasAccess('list'))
<a href="{{ route('hr.policy.upload.form') }}" class="btn btn-primary btn-sm">
    <i class="la la-upload"></i> Upload new policy
</a>
@endif
