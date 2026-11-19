<?php

namespace App\Exceptions;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->renderable(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            $route = $request->route();
            if ($request->isMethod('DELETE') && $route?->getAction('operation') === 'delete'
                && is_a($route->getControllerClass(), CrudController::class, true)) {
                return response()->json(['error' => collect($e->errors())->flatten()->values()->all()]);
            }
        });
        $this->renderable(function (QueryException $e, Request $request) {
            $route = $request->route();
            if (!$request->isMethod('DELETE') || !$route
                || $route->getAction('operation') !== 'delete'
                || !is_a($route->getControllerClass(), CrudController::class, true)
                || !$this->isReferencedRecordDelete($e)) {
                return null;
            }

            // Backpack expects HTTP 200 with notification arrays for a refused
            // deletion. Only a scalar 1 removes the row and shows success.
            return response()->json([
                'error' => [
                    'Cannot delete this record because other records still use it. Reassign or remove those references first, then try again.',
                ],
            ]);
        });

        $this->reportable(function (Throwable $e) {
            //
        });
    }

    private function isReferencedRecordDelete(QueryException $e): bool
    {
        if (!preg_match('/^\s*delete\b/i', $e->getSql())) {
            return false;
        }

        $code = (int) ($e->errorInfo[1] ?? 0);
        $message = strtolower($e->errorInfo[2] ?? '');

        return match ($code) {
            // SQL Server also uses 547 for CHECK constraints; only handle references.
            547 => str_contains($message, 'reference constraint')
                || str_contains($message, 'foreign key constraint'),
            1451 => true, // MySQL: parent row is referenced.
            19, 787 => str_contains($message, 'foreign key constraint'), // SQLite.
            default => false,
        };
    }
}
