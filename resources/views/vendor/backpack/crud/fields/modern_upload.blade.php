@php
    $field['name'] = $field['name'] ?? 'attachments';
    $field['label'] = $field['label'] ?? 'Attachments';
    $field['disk'] = $field['disk'] ?? 'public';

    // 1. Retrieve already saved files from the database (Update operation fallback)
    $existingFiles = [];
    if (isset($field['value'])) {
        if (is_array($field['value'])) {
            $existingFiles = $field['value'];
        } elseif (is_string($field['value'])) {
            $existingFiles = json_decode($field['value'], true) ?? [];
        }
    }
@endphp

<div class="form-group" bp-field-name="{{ $field['name'] }}">
    <label>{!! $field['label'] !!}</label>

    <div id="drop-zone-{{ $field['name'] }}" class="border rounded p-4 text-center"
         style="border-style: dashed !important; cursor: pointer; background: #f8f9fa; transition: background 0.15s ease-in-out;">
        <i class="la la-cloud-upload-alt" style="font-size: 2.2rem; color: #6c757d;"></i>
        <p class="mb-1 mt-2">Drag &amp; drop file(s) here, or click to browse</p>
        <small class="text-muted">Maximum single file size: 20MB</small>

        <input type="file" name="{{ $field['name'] }}[]" id="input-{{ $field['name'] }}"
               multiple style="display: none;">
    </div>

    <div id="preview-container-{{ $field['name'] }}" class="mt-2 {{ count($existingFiles) > 0 ? '' : 'd-none' }}">
        <label class="text-muted small font-weight-bold mb-1">Files for this ticket:</label>
        <div id="file-list-{{ $field['name'] }}" class="d-flex flex-column gap-2">

            @foreach($existingFiles as $index => $filePath)
                @php
                    $filename = basename($filePath);
                @endphp
                <div class="alert alert-secondary d-flex align-items-center mb-1 py-2 px-3 m-0 rounded existing-file-item"
                     style="gap: 10px;" data-file-path="{{ $filePath }}">
                    <i class="la la-paperclip text-primary" style="font-size:1.2rem;"></i>
                    <div class="text-truncate mr-2" style="max-width: 75%;">
                        <a href="{{ Storage::disk($field['disk'])->url($filePath) }}" target="_blank" class="font-weight-bold text-dark">
                            {{ $filename }}
                        </a>
                    </div>
                    <input type="hidden" name="{{ $field['name'] }}[]" value="{{ $filePath }}" class="retain-file-input">

                    <button type="button" class="close ml-auto p-0 text-danger remove-existing-btn" style="font-size: 1.4rem; line-height: 1;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endforeach

        </div>
        <div id="new-file-list-{{ $field['name'] }}" class="d-flex flex-column gap-2 mt-1"></div>
    </div>

    @if ($errors->has($field['name']))
        <small class="text-danger mt-1 d-block">{{ $errors->first($field['name']) }}</small>
    @endif
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const dropZone = document.getElementById("drop-zone-{{ $field['name'] }}");
    const fileInput = document.getElementById("input-{{ $field['name'] }}");
    const previewContainer = document.getElementById("preview-container-{{ $field['name'] }}");
    const newFileList = document.getElementById("new-file-list-{{ $field['name'] }}");
    const mainFileList = document.getElementById("file-list-{{ $field['name'] }}");

    // Click to browse
    dropZone.addEventListener("click", () => fileInput.click());

    // Drag interactions
    dropZone.addEventListener("dragover", (e) => { e.preventDefault(); dropZone.style.background = "#e8f4f8"; });
    dropZone.addEventListener("dragleave", () => { dropZone.style.background = "#f8f9fa"; });
    dropZone.addEventListener("drop", (e) => {
        e.preventDefault();
        dropZone.style.background = "#f8f9fa";
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            updateNewFilePreviews(fileInput.files);
        }
    });

    // Handle new input selections
    fileInput.addEventListener("change", function () {
        if (this.files.length) updateNewFilePreviews(this.files);
    });

    // Handle removal of existing files
    mainFileList.addEventListener('click', function(e) {
        const removeBtn = e.target.closest('.remove-existing-btn');
        if (removeBtn) {
            const itemRow = removeBtn.closest('.existing-file-item');

            // This safely removes the row and its inner hidden input from the form payload entirely
            itemRow.remove();
            checkVisibility();
        }
    });

    function updateNewFilePreviews(files) {
        newFileList.innerHTML = "";
        previewContainer.classList.remove("d-none");

        Array.from(files).forEach((file, index) => {
            const sizeInMB = (file.size / 1024 / 1024).toFixed(2);
            const item = document.createElement("div");
            item.className = "alert alert-secondary d-flex align-items-center mb-1 py-2 px-3 m-0 rounded existing-file-item";
            item.style.gap = "10px";
            item.innerHTML = `
                <i class="la la-paperclip text-primary" style="font-size:1.2rem;"></i>
                <div class="text-truncate mr-2" style="max-width: 75%;">
                    <strong class="text-dark">${file.name}</strong>
                    <span class="text-dark small ml-1">(${sizeInMB} MB)</span>
                </div>
                <button type="button" class="close ml-auto p-0 text-danger" style="font-size: 1.4rem; line-height: 1;">
                    <span aria-hidden="true">&times;</span>
                </button>
            `;

            item.querySelector('.close').addEventListener('click', (e) => {
                e.stopPropagation();
                removeNewFile(index);
            });

            newFileList.appendChild(item);
        });
    }

    function removeNewFile(indexToRemove) {
        const dataTransfer = new DataTransfer();
        const { files } = fileInput;

        for (let i = 0; i < files.length; i++) {
            if (i !== indexToRemove) {
                dataTransfer.items.add(files[i]);
            }
        }

        fileInput.files = dataTransfer.files;
        updateNewFilePreviews(fileInput.files);
        checkVisibility();
    }

    function checkVisibility() {
        var hasExisting = mainFileList.querySelectorAll('.existing-file-item').length > 0;
        var hasNew = newFileList.children.length > 0;

        if (!hasExisting && !hasNew) {
            previewContainer.classList.add("d-none");
        } else {
            previewContainer.classList.remove("d-none");
        }
    }
});
</script>
