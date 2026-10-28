<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

try {
    $authorized = ($argv[4] ?? '') === 'authorized-data';
    if ($authorized) {
        \Tests\Support\AuthorizedEngineDatabase::boot();
        $owner = \Tests\Support\AuthorizedEngineDatabase::assertOwner($argv[3] ?? '');
    } else {
        \Tests\Support\EngineDatabase::boot();
        \Tests\Support\EngineDatabase::assertOwner($argv[3] ?? '');
    }
    $model = ($argv[1] ?? '') === 'survey' ? \App\Models\Survey::class : \App\Models\ArtaSurvey::class;
    $data = json_decode($argv[2] ?? '', true);
    if (!is_array($data)) { throw new \LogicException('Invalid synthetic worker payload.'); }
    if ($authorized && (($model === \App\Models\Survey::class && (int) ($data['user_id'] ?? 0) !== $owner)
        || ($model === \App\Models\ArtaSurvey::class && ($data['service_availed'] ?? '') !== 'ec_engine_probe_' . $argv[3]))) {
        throw new \LogicException('Worker payload is not owned by this probe.');
    }
    $waiting = true;
    \Illuminate\Support\Facades\DB::listen(function ($event) use (&$waiting) {
        // Barrier AFTER the real first SELECT, before either worker attempts INSERT.
        if ($waiting && preg_match('/^select\b/i', $event->sql) && strpos($event->sql, 'submission_id') !== false) {
            $waiting = false;
            fwrite(STDOUT, "READY\n"); fflush(STDOUT);
            if (trim((string) fgets(STDIN)) !== 'GO') { throw new \RuntimeException('Race barrier was not released.'); }
        }
    });
    try {
        $entry = \App\Services\SurveySubmission::save($model, $data);
        $result = ['status' => 'saved', 'id' => (int) $entry->id];
    } catch (\Illuminate\Validation\ValidationException $e) {
        $result = ['status' => 'rejected', 'field' => array_keys($e->errors())];
    }
    fwrite(STDOUT, 'RESULT ' . json_encode($result) . "\n");
} catch (\Throwable $e) {
    // Do not expose connection exceptions, SQL, or credentials to process output.
    fwrite(STDOUT, 'RESULT ' . json_encode(['status' => 'error', 'type' => get_class($e)]) . "\n");
    exit(1);
}
