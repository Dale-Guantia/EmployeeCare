<?php

// This CLI owns a disposable database and fake storage. Never boot against app data.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use App\Models\HrPolicyDocument;
use App\Services\AnthropicService;
use App\Services\PolicyIngestService;
use App\Services\PolicyRetriever;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Tests\Support\HrChatEvaluation;
use Tests\Support\IsolatedDatabase;

$isolatedStorage = null;
$exitCode = 0;
try {
    $options = getopt('', ['rebuild-corpus', 'output:', 'case:', 'live-answers', 'max-api-calls:', 'score:', 'help']);
    if (isset($options['help'])) {
        echo "Usage: php tests/Support/run-hr-chat-evaluation.php [--rebuild-corpus] [--output=relative/path.json] [--case=HR-EVAL-001]\n";
        echo "Offline only: actual ingestion/retrieval in SQLite :memory:; no AI calls or chat-log writes.\n";
        echo "Optional paid capture: --live-answers --case=HR-EVAL-001 --max-api-calls=1 (also sends selected policy text to Anthropic).\n";
        echo "Review scoring: --score=path/to/reviewed-report.json (offline; does not boot Laravel).\n";
        exit(0);
    }
    $datasetPath = $root . '/tests/Fixtures/hr-chat-evaluation/questions.json';
    $manifestPath = $root . '/tests/Fixtures/hr-chat-evaluation/sources.json';
    $dataset = HrChatEvaluation::read($datasetPath);
    $manifest = HrChatEvaluation::read($manifestPath);
    $datasetHash = hash_file('sha256', $datasetPath);
    $manifestHash = hash_file('sha256', $manifestPath);
    HrChatEvaluation::validate($dataset, $manifest);
    if (isset($options['score'])) {
        $reviewed = HrChatEvaluation::read($options['score']);
        if (($reviewed['dataset_sha256'] ?? '') !== $datasetHash || ($reviewed['manifest_sha256'] ?? '') !== $manifestHash) {
            throw new InvalidArgumentException('The review report belongs to a different dataset or source manifest.');
        }
        echo json_encode(HrChatEvaluation::scoreReviews($reviewed, $dataset), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
        exit(0);
    }
    $live = isset($options['live-answers']);
    if (isset($options['case'])) {
        $dataset['cases'] = array_values(array_filter($dataset['cases'], fn ($c) => $c['id'] === $options['case']));
        if (!$dataset['cases']) { throw new InvalidArgumentException('Unknown evaluation case ID.'); }
    }
    $maxCalls = filter_var($options['max-api-calls'] ?? 0, FILTER_VALIDATE_INT);
    if ($live && (!isset($options['case']) || $maxCalls === false || $maxCalls < 1 || $maxCalls > 20
        || $maxCalls < 1 + count($dataset['cases'][0]['prior_questions']))) {
        throw new InvalidArgumentException('Live capture requires one --case and --max-api-calls between its turn count and 20.');
    }
    $sourceRoot = $root . '/policy documents';
    foreach ($manifest['documents'] as $document) {
        foreach (array_merge([$document['filename']], $document['aliases']) as $filename) {
            if (basename($filename) !== $filename || !is_file($sourceRoot . '/' . $filename)
                || hash_file('sha256', $sourceRoot . '/' . $filename) !== $document['sha256']) {
                throw new RuntimeException('Missing or changed source PDF: ' . $filename . '. Review the manifest before rebaselining.');
            }
        }
    }
    $output = $options['output'] ?? 'storage/app/hr-chat-evaluation/latest.json';
    if (!preg_match('~^(?:storage/app/hr-chat-evaluation|docs/hr-chat-evaluation)/[a-zA-Z0-9_-]+\.json$~', str_replace('\\', '/', $output))) {
        throw new RuntimeException('Output must be a named JSON file under storage/app/hr-chat-evaluation or docs/hr-chat-evaluation.');
    }
    $outputPath = $root . '/' . $output;
    if (is_file($outputPath)) {
        throw new RuntimeException('Output already exists. Use a new filename to preserve the previous baseline.');
    }
    // A cached app configuration could select the application DB despite env overrides.
    // Refuse it rather than clearing the user's cache or risking a connection.
    foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
        'DATABASE_URL' => '', 'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array'] as $name => $value) {
        putenv($name . '=' . $value); $_ENV[$name] = $_SERVER[$name] = $value;
    }
    $app = require $root . '/bootstrap/app.php';
    if ($app->configurationIsCached()) { throw new RuntimeException('Evaluation refuses cached application configuration.'); }
    $isolatedStorage = $root . '/storage/framework/testing/hr-chat-evaluation-' . bin2hex(random_bytes(8));
    foreach (['framework/cache', 'framework/sessions', 'framework/views', 'logs'] as $directory) {
        mkdir($isolatedStorage . '/' . $directory, 0777, true);
    }
    $app->useStoragePath($isolatedStorage);
    $app->make(Kernel::class)->bootstrap();
    IsolatedDatabase::prepare($app);
    $fingerprint = [];
    foreach (['app/Services/PolicyIngestService.php', 'app/Services/OcrService.php'] as $file) {
        $fingerprint[$file] = hash_file('sha256', $root . '/' . $file);
    }
    $cacheIdentity = hash('sha256', json_encode([$manifest, $fingerprint, config('ocr'), config('services.ocr')], JSON_THROW_ON_ERROR));
    $cachePath = $root . '/storage/app/hr-chat-evaluation/corpus-' . $cacheIdentity . '.json';
    $corpus = [];
    if (is_file($cachePath) && !isset($options['rebuild-corpus'])) {
        $corpus = HrChatEvaluation::read($cachePath);
        echo "Using extraction cache; all PDF hashes verified.\n";
    } else {
        foreach ($manifest['documents'] as $source) {
            echo 'Ingesting ' . $source['filename'] . "\n";
            $file = new UploadedFile($sourceRoot . '/' . $source['filename'], $source['filename'], 'application/pdf', UPLOAD_ERR_OK, true);
            $service = $app->make(PolicyIngestService::class);
            $document = $service->store($file, ['title' => $source['title'], 'category' => $source['category']]);
            try {
                $service->ingest($document);
            } catch (Throwable $error) {
                // Failed documents remain in the inventory, outside retrieval.
                echo '  Ingestion failed (' . get_class($error) . ").\n";
            }
            $document->refresh();
            // Preserve the application's quality gate; never activate needs_review/failed data.
            $corpus[] = ['key' => $source['key'], 'sha256' => $source['sha256'],
                'document' => $document->only(['title', 'filename', 'category', 'status', 'is_active', 'chunk_count', 'ocr_used', 'ocr_confidence']),
                'chunks' => $document->chunks()->orderBy('chunk_index')->get()->map(fn ($c) => $c->only(['chunk_index', 'page_number', 'section_title', 'content', 'search_vector']))->all()];
            echo '  ' . $document->status . ', chunks=' . $document->chunk_count . ', OCR=' . ($document->ocr_used ? 'yes' : 'no') . "\n";
        }
        if (!is_dir(dirname($cachePath))) { mkdir(dirname($cachePath), 0777, true); }
        file_put_contents($cachePath, json_encode($corpus, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        // Reimport below so cached and fresh runs have identical insertion/tie order.
        HrPolicyDocument::query()->each(function ($d) { $d->chunks()->delete(); $d->delete(); });
    }
    $documentKeys = [];
    foreach ($corpus as $entry) {
        $document = HrPolicyDocument::create($entry['document']);
        $documentKeys[$document->id] = $entry['key'];
        foreach ($entry['chunks'] as $chunk) { $document->chunks()->create($chunk); }
    }
    $apiCalls = 0;
    $answerer = null;
    if ($live) {
        // Explicit operator opt-in only. Keep offline networking blocked by default.
        Illuminate\Support\Facades\Http::swap(new Illuminate\Http\Client\Factory($app['events']));
        $answerer = function ($question, $context, $history) use ($app, &$apiCalls, $maxCalls) {
            if ($apiCalls >= $maxCalls) { throw new RuntimeException('Evaluation API budget exhausted.'); }
            $apiCalls++;
            return $app->make(AnthropicService::class)->ask($question, $context, $history);
        };
    }
    $baseline = (new HrChatEvaluation)->run($dataset, $documentKeys, $app->make(PolicyRetriever::class), $answerer);
    $codeFiles = ['app/Services/PolicyRetriever.php', 'app/Services/PolicyChatPrompt.php', 'app/Services/AnthropicService.php',
        'app/Http/Controllers/Admin/HrChatController.php', 'composer.lock',
        'tests/Support/HrChatEvaluation.php', 'tests/Support/run-hr-chat-evaluation.php'];
    foreach ($codeFiles as $file) { $fingerprint[$file] = hash_file('sha256', $root . '/' . $file); }
    if ($datasetHash !== hash_file('sha256', $datasetPath) || $manifestHash !== hash_file('sha256', $manifestPath)) {
        throw new RuntimeException('Evaluation inputs changed during the run. Rerun against the reviewed files.');
    }
    $baseline = array_merge([
        'schema_version' => 1, 'captured_at' => gmdate('c'), 'mode' => $live ? 'live_answer_capture' : 'offline_retrieval',
        'api_attempts' => $apiCalls,
        'provider' => $live ? ['name' => 'anthropic', 'model' => config('services.anthropic.model')] : null,
        'database' => 'sqlite :memory:', 'policy_authority' => 'unverified_fixture_corpus',
        'dataset_sha256' => $datasetHash, 'manifest_sha256' => $manifestHash,
        'corpus_sha256' => hash('sha256', json_encode($corpus, JSON_THROW_ON_ERROR)),
        'code_sha256' => $fingerprint, 'php' => PHP_VERSION, 'laravel' => $app->version(),
        'limitations' => ['Expected answers and acceptance criteria await HR approval.',
            'Evidence matching checks document key and normalized quote, not claim correctness or page accuracy.',
            $live ? 'Live answers await human evaluation; only the selected case and its prior turns were sent.'
                : 'Prior questions replay retrieval only; no assistant answers or live model behavior are evaluated.',
            'PDF page numbers are physical 1-based pages; chunk metadata records only the starting page.',
            'Fixture documents have no HR-approved precedence or effective-date configuration.',
            'This does not certify the live application corpus, SQL Server, browser behavior, or OCR visual fidelity.'],
        'corpus' => array_map(fn ($entry) => ['key' => $entry['key'], 'sha256' => $entry['sha256']] + $entry['document'], $corpus),
    ], $baseline);
    if (!is_dir(dirname($outputPath))) { mkdir(dirname($outputPath), 0777, true); }
    $handle = fopen($outputPath, 'x');
    if (!$handle) { throw new RuntimeException('Could not create the baseline output.'); }
    fwrite($handle, json_encode($baseline, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    fclose($handle);
    echo json_encode($baseline['summary'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\nSaved: " . $output . "\n";
} catch (Throwable $error) {
    // Do not include transport exception messages, config, prompts or credentials.
    fwrite(STDERR, 'Evaluation failed (' . get_class($error) . '). ' .
        ($error instanceof InvalidArgumentException || $error instanceof RuntimeException ? $error->getMessage() : 'Inspect the local test log.') . "\n");
    $exitCode = 1;
} finally {
    if ($isolatedStorage !== null && is_dir($isolatedStorage)) {
        // Generated task-owned storage only; source PDFs and extraction cache live elsewhere.
        (new Illuminate\Filesystem\Filesystem)->deleteDirectory($isolatedStorage);
    }
}
exit($exitCode);
