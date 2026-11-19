<?php

namespace App\Http\Controllers\Admin\Operations;

use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

trait MasterDataArchivingOperation
{
    protected function setupMasterDataArchivingRoutes($segment, $routeName, $controller)
    {
        foreach (['archive', 'reactivate'] as $action) {
            Route::post($segment . '/{id}/' . $action, [
                'as' => $routeName . '.' . $action, 'uses' => $controller . '@' . $action,
                'operation' => 'masterDataArchiving',
            ]);
        }
        Route::get($segment . '/{id}/delete-impact', [
            'as' => $routeName . '.delete-impact', 'uses' => $controller . '@deleteImpact',
            'operation' => 'masterDataArchiving',
        ]);
    }

    public function deleteImpact($id)
    {
        $this->crud->hasAccessOrFail('delete');
        return response()->json(\App\Services\MasterDataRules::references($this->crud->model->newQuery()->findOrFail($id)))
            ->header('Cache-Control', 'no-store, private');
    }

    protected function setupMasterDataArchivingDefaults(): void
    {
        foreach (['archive', 'reactivate'] as $action) {
            $this->crud->setAccessCondition($action, fn () => backpack_user()->can($this->masterDataEntity() . '.' . $action));
        }
    }

    private function masterDataEntity(): string
    {
        return match ($this->crud->model->getTable()) {
            'departments' => 'department', 'divisions' => 'division', 'issues' => 'issue',
            'priorities' => 'priority', 'statuses' => 'status',
        };
    }

    public function archive($id)
    {
        return $this->setMasterDataActive($id, false);
    }

    public function reactivate($id)
    {
        return $this->setMasterDataActive($id, true);
    }

    private function setMasterDataActive($id, bool $active)
    {
        abort_unless(backpack_user()->can($this->masterDataEntity() . ($active ? '.reactivate' : '.archive')), 403);
        try {
            request()->validate(['reason' => 'nullable|string|max:1000']);
            $this->crud->model->newQuery()->findOrFail($id)->update(['is_active' => $active]);
        } catch (ValidationException $e) {
            if (request()->expectsJson()) {
                throw $e;
            }
            \Alert::error(collect($e->errors())->flatten()->implode(' '))->flash();
            return redirect($this->crud->route);
        }
        $message = ucfirst($this->masterDataEntity()) . ($active ? ' reactivated. Individually archived children stay archived.' : ' archived. Historical records were preserved.');
        if (request()->expectsJson()) {
            return response()->json(['message' => $message]);
        }
        \Alert::success($message)->flash();
        return redirect($this->crud->route);
    }
}
