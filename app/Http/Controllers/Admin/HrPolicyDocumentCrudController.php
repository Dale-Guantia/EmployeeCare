<?php

namespace App\Http\Controllers\Admin;

use App\Models\HrPolicyDocument;
use App\Services\PolicyIngestService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;

class HrPolicyDocumentCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(HrPolicyDocument::class);
        CRUD::setRoute(backpack_url('hr-policy-documents'));
        CRUD::setEntityNameStrings('HR policy document', 'HR policy documents');

        CRUD::allowAccess(['upload_new', 'update_policy', 'toggle_active']);

        // Only admins and HR staff can manage policies
        $this->middleware(function ($request, $next) {
            if (!backpack_user()->hasAnyRole(['admin', 'hr_staff'])) {
                abort(403, 'Only HR staff can manage policy documents.');
            }
            return $next($request);
        });
    }

    protected function setupListOperation()
    {
        CRUD::column('title')->label('Document title');
        CRUD::column('category')->label('Category');
        CRUD::column('chunk_count')->label('Chunks');
        CRUD::column('effective_date')->label('Effective date')->type('date');
        CRUD::column('status_badge')
            ->label('Status')
            ->type('custom_html')
            ->value(function ($entry) {
                return $entry->status_badge;
            });
        CRUD::column('created_at')->label('Uploaded')->type('datetime');

        // Custom buttons
        CRUD::button('upload_new')->stack('top')->view('admin.buttons.policy_upload_btn');
        CRUD::button('update_policy')->stack('line')->view('admin.buttons.policy_update_btn');
        CRUD::button('toggle_active')->stack('line')->view('admin.buttons.policy_toggle_btn');
        CRUD::removeButton('create'); // replaced by custom upload page
    }

    // ── Custom upload (create) ─────────────────────────────────

    public function uploadForm()
    {
        $this->crud->hasAccessOrFail('list');
        $categories = ['general', 'leave', 'benefits', 'performance'];
        return view('admin.hr_policy_upload', compact('categories'));
    }

    public function uploadStore(Request $request, PolicyIngestService $ingest)
    {
        $this->crud->hasAccessOrFail('list');

        $request->validate([
            'title'          => 'required|string|max:255',
            'category'       => 'required|string',
            'effective_date' => 'nullable|date',
            'pdf_file'       => 'required|file|mimes:pdf|max:20480', // 20MB max
        ]);

        try {
            $document = $ingest->store(
                $request->file('pdf_file'),
                $request->only('title', 'category', 'effective_date')
            );

            // Run ingestion inline (synchronous for simplicity on PHP 7)
            $ingest->ingest($document);

            \Alert::success("Policy \"{$document->title}\" uploaded and ingested successfully. {$document->chunk_count} chunks created.")->flash();

        } catch (\Throwable $e) {
            \Alert::error('Upload failed: ' . $e->getMessage())->flash();
        }

        return redirect(backpack_url('hr-policy-documents'));
    }

    // ── Custom update (replace PDF) ────────────────────────────

    public function updateForm(HrPolicyDocument $document)
    {
        $this->crud->hasAccessOrFail('list');
        $categories = ['general', 'leave', 'benefits', 'performance'];
        return view('admin.hr_policy_update', compact('document', 'categories'));
    }

    public function updateStore(Request $request, HrPolicyDocument $document, PolicyIngestService $ingest)
    {
        $this->crud->hasAccessOrFail('list');

        $rules = [
            'title'          => 'required|string|max:255',
            'category'       => 'required|string',
            'effective_date' => 'nullable|date',
            'pdf_file'       => 'nullable|file|mimes:pdf|max:20480',
        ];

        $request->validate($rules);

        try {
            if ($request->hasFile('pdf_file')) {
                // Full replace: new file + re-ingest
                $ingest->replace(
                    $document,
                    $request->file('pdf_file'),
                    $request->only('title', 'category', 'effective_date')
                );
                $msg = "Policy updated and re-ingested. {$document->fresh()->chunk_count} chunks created.";
            } else {
                // Metadata-only update — no re-ingest needed
                $document->update($request->only('title', 'category', 'effective_date'));
                $msg = 'Policy metadata updated.';
            }

            \Alert::success($msg)->flash();

        } catch (\Throwable $e) {
            \Alert::error('Update failed: ' . $e->getMessage())->flash();
        }

        return redirect(backpack_url('hr-policy-documents'));
    }

    // ── Toggle active/inactive ─────────────────────────────────

    public function toggleActive(HrPolicyDocument $document)
    {
        $this->crud->hasAccessOrFail('list');

        $document->update([
            'is_active' => !$document->is_active,
            'status'    => $document->is_active ? 'inactive' : 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Document marked as " . ($document->fresh()->is_active ? 'active' : 'inactive'),
        ]);
    }

    // ── Override delete to also clean up file + chunks ─────────

    public function destroy($id)
    {
        $this->crud->hasAccessOrFail('delete');

        $document = HrPolicyDocument::findOrFail($id);
        app(PolicyIngestService::class)->destroy($document);

        return response()->json(['success' => true]);
    }
}
