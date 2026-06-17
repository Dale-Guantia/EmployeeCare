@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid d-flex mb-2 mt-2">
    <h2><i class="la la-upload"></i> Upload HR Policy Document</h2>
</section>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('hr.policy.upload.store') }}"
                      method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="form-group">
                        <label>Document title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control"
                               value="{{ old('title') }}"
                               placeholder="e.g. Leave Policy Manual 2025">
                        @error('title')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label>Category <span class="text-danger">*</span></label>
                        <select name="category" class="form-control">
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}" {{ old('category') == $cat ? 'selected' : '' }}>
                                    {{ ucfirst($cat) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Effective date</label>
                        <input type="date" name="effective_date" class="form-control"
                               value="{{ old('effective_date') }}">
                    </div>

                    <div class="form-group">
                        <label>PDF file <span class="text-danger">*</span></label>
                        <div id="drop-zone" class="border rounded p-4 text-center"
                             style="border-style:dashed !important;cursor:pointer;background:#f8f9fa;">
                            <i class="la la-file-pdf" style="font-size:2rem;color:#dc3545;"></i>
                            <p class="mb-1 mt-2">Drag &amp; drop PDF here, or click to browse</p>
                            <small class="text-muted">Maximum file size: 20MB</small>
                            <input type="file" name="pdf_file" id="pdf_file"
                                   accept=".pdf" style="display:none;">
                        </div>
                        <div id="file-preview" class="mt-2 d-none">
                            <div class="alert alert-secondary d-flex align-items-center gap-2 mb-0">
                                <i class="la la-paperclip text-primary" style="font-size:1.2rem;"></i>
                                <span id="file-name" class="font-weight-bold text-dark"></span>
                                <span id="file-size" class="text-dark ml-2"></span>
                                <button type="button" class="close ml-auto p-0 text-danger remove-existing-btn" style="font-size: 1.4rem; line-height: 1;" onclick="clearFile()">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        </div>
                        @error('pdf_file')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="alert alert-warning text-dark">
                        <i class="la la-info-circle"></i>
                        After uploading, the system will automatically parse and index the PDF.
                        This may take a few seconds depending on file size.
                        The chatbot will use this document immediately after uploading.
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success" id="submit-btn">
                            <i class="la la-upload"></i> Upload &amp; Ingest
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

dropZone.addEventListener('dragover', function(e) {
    e.preventDefault();
    dropZone.style.background = '#e8f4f8';
});

dropZone.addEventListener('dragleave', function() {
    dropZone.style.background = '#f8f9fa';
});

dropZone.addEventListener('drop', function(e) {
    e.preventDefault();
    dropZone.style.background = '#f8f9fa';
    var files = e.dataTransfer.files;
    if (files.length) {
        fileInput.files = files;
        showPreview(files[0]);
    }
});

fileInput.addEventListener('change', function() {
    if (this.files.length) showPreview(this.files[0]);
});

function showPreview(file) {
    document.getElementById('file-name').textContent = file.name;
    document.getElementById('file-size').textContent =
        '(' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
    document.getElementById('file-preview').classList.remove('d-none');
    dropZone.style.display = 'none';
}

function clearFile() {
    fileInput.value = '';
    document.getElementById('file-preview').classList.add('d-none');
    dropZone.style.display = '';
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
