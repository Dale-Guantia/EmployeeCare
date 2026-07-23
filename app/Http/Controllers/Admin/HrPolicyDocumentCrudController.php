<?php

namespace App\Http\Controllers\Admin;

use App\Jobs\IngestPolicyDocumentJob;
use App\Models\HrPolicyDocument;
use App\Services\PolicyIngestService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use Prologue\Alerts\Facades\Alert;

class HrPolicyDocumentCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(HrPolicyDocument::class);
        CRUD::setRoute(backpack_url('hr-policy-documents'));
        CRUD::setEntityNameStrings('HR policy document', 'HR policy documents');

        CRUD::allowAccess(['upload_new', 'update_policy', 'toggle_active', 'reingest']);

        // Only admin, dept_head can view/manage policy documents.
        // Must be a direct check here, NOT wrapped in $this->middleware(...) —
        // Backpack's own CrudController::__construct() registers setup() itself
        // inside a middleware closure, so by the time setup() runs, Laravel's
        // middleware pipeline for this request has already been built. Any
        // middleware added via $this->middleware() from within setup() is added
        // too late to actually run before the action — it silently never fires,
        // meaning a guard written that way appears to work but blocks no one
        // (confirmed: even the 'employee' role was getting through). A plain
        // synchronous check here runs immediately, in-line, every request.
        if (!backpack_user()->hasAnyRole(['admin', 'dept_head'])) {
            abort(403, 'You are not allowed to access policy documents.');
        }
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
        CRUD::column('ocr_badge')
            ->label('Type')
            ->type('custom_html')
            ->value(function ($entry) {
                return $entry->ocr_badge;
            });
        CRUD::column('review_note')
            ->label('Notes')
            ->type('custom_html')
            ->value(function ($entry) {
                if (!in_array($entry->status, ['needs_review', 'failed'], true) || empty($entry->ingest_error)) {
                    return '';
                }
                $icon  = $entry->status === 'failed' ? 'la-times-circle text-danger' : 'la-exclamation-triangle text-warning';
                return '<span title="' . e($entry->ingest_error) . '"><i class="la ' . $icon . '"></i> '
                    . e(\Illuminate\Support\Str::limit($entry->ingest_error, 60)) . '</span>';
            });
        CRUD::column('created_at')->label('Uploaded')->type('datetime');

        // Custom buttons
        CRUD::button('upload_new')->stack('top')->view('admin.buttons.policy_upload_btn');
        CRUD::button('update_policy')->stack('line')->view('admin.buttons.policy_update_btn');
        CRUD::button('toggle_active')->stack('line')->view('admin.buttons.policy_toggle_btn');
        CRUD::button('reingest')->stack('line')->view('admin.buttons.policy_reingest_btn');
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
        set_time_limit(0);
        $this->crud->hasAccessOrFail('list');

        $request->validate([
            'title'          => 'required|string|max:255',
            'category'       => 'required|in:general,leave,benefits,performance',
            'effective_date' => 'nullable|date',
            'pdf_file'       => 'required|file|mimes:pdf|max:20480', // 20MB max
        ]);

        try {
            $document = $ingest->store(
                $request->file('pdf_file'),
                $request->only('title', 'category', 'effective_date')
            );

            IngestPolicyDocumentJob::dispatch($document->id);

            Alert::success("Policy \"{$document->title}\" uploaded. Processing in the background — refresh this list in a moment to see its status.")->flash();

        } catch (\Throwable $e) {
            Alert::error('Upload failed: ' . $e->getMessage())->flash();
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
        set_time_limit(0);
        $this->crud->hasAccessOrFail('list');

        $rules = [
            'title'          => 'required|string|max:255',
            'category'       => 'required|in:general,leave,benefits,performance',
            'effective_date' => 'nullable|date',
            'pdf_file'       => 'nullable|file|mimes:pdf|max:20480',
        ];

        $request->validate($rules);

        try {
            if ($request->hasFile('pdf_file')) {
                // Full replace: new file, then re-ingest in the background
                $ingest->replace(
                    $document,
                    $request->file('pdf_file'),
                    $request->only('title', 'category', 'effective_date')
                );
                IngestPolicyDocumentJob::dispatch($document->id, true);
                $msg = 'Policy file replaced. Processing in the background — refresh this list in a moment to see its status.';
            } else {
                // Metadata-only update — no re-ingest needed
                $document->update($request->only('title', 'category', 'effective_date'));
                $msg = 'Policy metadata updated.';
            }

            Alert::success($msg)->flash();

        } catch (\Throwable $e) {
            Alert::error('Update failed: ' . $e->getMessage())->flash();
        }

        return redirect(backpack_url('hr-policy-documents'));
    }

    // ── Toggle active/inactive ─────────────────────────────────

    public function toggleActive(HrPolicyDocument $document)
    {
        $this->crud->hasAccessOrFail('list');

        // Only meaningful for a document that has actually finished ingesting
        // one way or another — flipping a pending/processing doc to 'active'
        // here would bypass the quality gate that needs_review exists for.
        if (!in_array($document->status, ['active', 'inactive'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This document is ' . $document->status . ' — it can only be toggled active/inactive once ingestion has finished.',
            ], 422);
        }

        $document->update([
            'is_active' => !$document->is_active,
            'status'    => $document->is_active ? 'inactive' : 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Document marked as " . ($document->fresh()->is_active ? 'active' : 'inactive'),
        ]);
    }

    // ── Re-run ingestion in place (no delete/recreate) ─────────

    public function reingest(HrPolicyDocument $document)
    {
        $this->crud->hasAccessOrFail('list');

        app(PolicyIngestService::class)->markPendingForReingest($document);
        IngestPolicyDocumentJob::dispatch($document->id, true);

        return response()->json([
            'success' => true,
            'message' => 'Re-ingestion started — refresh in a moment to see its status.',
        ]);
    }

    // ── Override delete to also clean up file + chunks ─────────

    public function destroy($id)
    {
        $this->crud->hasAccessOrFail('delete');

        $document = HrPolicyDocument::findOrFail($id);

        try {
            app(PolicyIngestService::class)->destroy($document);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[HrPolicyDocumentCrudController] Failed to delete document ' . $id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Could not delete this document: ' . $e->getMessage(),
            ], 500);
        }

        // Backpack's default delete AJAX handler checks `result == 1` to
        // decide whether to redraw the DataTable — a JSON object here would
        // be treated as notification-bubble data instead, so the row would
        // stay visible until a manual page refresh.
        return 1;
    }
}
