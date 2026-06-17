{{-- Per-row active/inactive toggle --}}
@if($crud->hasAccess('list'))
<button class="btn btn-sm {{ $entry->is_active ? 'btn-secondary' : 'btn-success' }}"
    onclick="togglePolicy({{ $entry->id }}, this)"
    title="{{ $entry->is_active ? 'Deactivate' : 'Activate' }}">
    <i class="la {{ $entry->is_active ? 'la-eye-slash' : 'la-eye' }}"></i>
    {{ $entry->is_active ? 'Deactivate' : 'Activate' }}
</button>
<script>
function togglePolicy(id, btn) {
    btn.disabled = true;
    fetch('{{ backpack_url("hr-policy-documents") }}/' + id + '/toggle', {
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
