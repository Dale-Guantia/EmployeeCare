@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid d-flex mb-2 mt-2">
    <h2><i class="la la-edit"></i> Update Policy Document</h2>
</section>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">

        {{-- Current document info --}}
        <div class="card mb-3 border-info">
            <div class="card-header bg-light">
                <strong>Current document</strong>
            </div>
            <div class="card-body py-2">
                <div class="row">
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Title</small>
                        <strong>{{ $document->title }}</strong>
                    </div>
                    <div class="col-sm-3">
                        <small class="text-muted d-block">Chunks</small>
                        <strong>{{ $document->chunk_count }}</strong>
                    </div>
                    <div class="col-sm-3">
                        <small class="text-muted d-block">Status</small>
                        {!! $document->status_badge !!}
                    </div>
                </div>
                @if($document->ingest_error)
                <div class="alert alert-danger mt-2 mb-0 py-2">
                    <small><strong>Last error:</strong> {{ $document->ingest_error }}</small>
                </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('hr.policy.update.store', $document) }}"
                      method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="form-group">
                        <label>Document title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control"
                               value="{{ old('title', $document->title) }}">
                    </div>

                    <div class="form-group">
                        <label>Category <span class="text-danger">*</span></label>
                        <select name="category" class="form-control">
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}"
                                    {{ old('category', $document->category) == $cat ? 'selected' : '' }}>
                                    {{ ucfirst($cat) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Effective date</label>
                        <input type="date" name="effective_date" class="form-control"
                               value="{{ old('effective_date', optional($document->effective_date)->format('Y-m-d')) }}">
                    </div>

                    <div class="form-group">
                        <label>Replace PDF file
                            <small class="text-muted">(leave empty to keep existing file)</small>
                        </label>
                        <div id="drop-zone" class="border rounded p-3 text-center"
                             style="border-style:dashed !important;cursor:pointer;background:#f8f9fa;">
                            <i class="la la-file-pdf" style="font-size:2rem;color:#dc3545;"></i>
                            <p class="mb-0 mt-1 small">
                                Drop new PDF here or click to browse
                            </p>
                            <input type="file" name="pdf_file" id="pdf_file"
                                   accept=".pdf" style="display:none;">
                        </div>
                        <div id="file-preview" class="mt-2 d-none">
                            <div class="alert alert-warning d-flex align-items-center mb-0 py-2 text-dark">
                                <i class="la la-exclamation-triangle mr-2" style="font-size:1.5rem;"></i>
                                <span>
                                    <strong id="file-name"></strong> will replace the existing PDF.
                                    All {{ $document->chunk_count }} existing chunks will be deleted and re-created.
                                </span>
                                <button type="button" class="close ml-auto p-0 text-danger remove-existing-btn" style="font-size: 1.4rem; line-height: 1;" onclick="clearFile()">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="submit-btn">
                            <i class="la la-save"></i> Save changes
                        </button>
                        <a href="{{ backpack_url('hr-policy-documents') }}"
                           class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script>
var dropZone = document.getElementById('drop-zone');
var fileInput = document.getElementById('pdf_file');
dropZone.addEventListener('click', function() { fileInput.click(); });
dropZone.addEventListener('dragover', function(e) { e.preventDefault(); });
dropZone.addEventListener('drop', function(e) {
    e.preventDefault();
    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        showPreview(e.dataTransfer.files[0]);
    }
});
fileInput.addEventListener('change', function() {
    if (this.files.length) showPreview(this.files[0]);
});
function showPreview(file) {
    document.getElementById('file-name').textContent = file.name;
    document.getElementById('file-preview').classList.remove('d-none');
}
function clearFile() {
    fileInput.value = '';
    document.getElementById('file-preview').classList.add('d-none');
}
document.querySelector('form').addEventListener('submit', function() {
    var btn = document.getElementById('submit-btn');

    // Add a tiny delay so the browser has time to trigger the actual POST request
    setTimeout(function() {
        btn.disabled = true;
        btn.innerHTML = '<i class="la la-spinner la-spin"></i> Processing…';
    }, 10);
});
</script>
@endpush
