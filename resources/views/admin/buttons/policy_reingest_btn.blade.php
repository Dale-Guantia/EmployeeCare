{{-- Re-run ingestion in place (no delete/recreate) --}}
@if($crud->hasAccess('list') && $entry->status !== 'processing')
<button class="btn btn-sm btn-outline-primary"
    onclick="reingestPolicy({{ $entry->id }}, this)"
    title="Re-run ingestion (e.g. after installing missing OCR language data, or to retry a failed/flagged document)">
    <i class="la la-redo"></i> Re-run
</button>
<script>
function reingestPolicy(id, btn) {
    btn.disabled = true;
    fetch('{{ backpack_url("hr-policy-documents") }}/' + id + '/reingest', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    })
    .then(function(r) { return r.json(); })
    .then(function() { location.reload(); })
    .catch(function() { btn.disabled = false; alert('Failed. Please try again.'); });
}
</script>
@endif
