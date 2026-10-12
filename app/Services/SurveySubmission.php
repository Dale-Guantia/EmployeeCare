<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SurveySubmission
{
    public static function save(string $model, array $data)
    {
        $id = strtolower($data['submission_id']);
        $data['submission_id'] = $id;
        try {
            $entry = DB::transaction(function () use ($model, $data, $id) {
                return $model::firstOrCreate(['submission_id' => $id], $data);
            });
        } catch (QueryException $e) {
            // The unique constraint resolves two clients/workers racing on the same ID.
            $code = (int) ($e->errorInfo[1] ?? 0);
            if (!in_array($code, [19, 1062, 2601, 2627], true)
                || !preg_match('/(?:\.submission_id|submission_id_unique)/i', $e->getMessage())) {
                throw $e;
            }
            $entry = $model::where('submission_id', $id)->first();
            if (!$entry) {
                throw $e;
            }
        }
        foreach ((new $model)->getFillable() as $key) {
            // SQL Server may return uniqueidentifier values with uppercase letters.
            if ($key === 'submission_id') { continue; }
            $value = $data[$key] ?? null;
            if ((string) $entry->getAttribute($key) !== (string) $value) {
                throw ValidationException::withMessages(['submission_id' => 'This submission was already saved with different answers. Start a new survey.']);
            }
        }
        return $entry;
    }
}
