@extends('crud::list')

@section('before_content_widgets')
    @parent
    <div class="alert alert-info py-2" id="policy-processing-status" role="status"
         data-status-url="{{ route('hr.policy.ingestion_status') }}"
         data-pending="{{ \App\Models\HrPolicyDocument::whereIn('status', ['pending', 'processing'])->exists() ? '1' : '0' }}" hidden>
        Document processing status updates automatically. Pending documents need the policy worker to be running.
    </div>
@endsection

@push('after_scripts')
    <script src="{{ asset('js/policy-status.js') }}"></script>
@endpush
