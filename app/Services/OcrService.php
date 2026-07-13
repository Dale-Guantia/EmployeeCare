<?php
namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * OcrService — cross-platform, per-page PDF text extraction
 *
 * Per page:
 *   Stage 1: smalot/pdfparser (fast, no shell, works on text-layer pages)
 *   Stage 2: pdftoppm + tesseract (only for pages whose Stage 1 text is too sparse)
 *
 * This means a single PDF can be "mixed" — most pages keep their native text,
 * only image/scanned pages are rasterized and OCR'd.
 *
 * Windows XAMPP installation:
 * Tesseract: https://github.com/UB-Mannheim/tesseract/wiki
 * Poppler:   https://github.com/oschwartz10612/poppler-windows/releases
 * Both must be configured via OCR_TESSERACT_PATH / OCR_PDFTOPPM_PATH in .env
 */
class OcrService
{
    protected $tesseractPath;
    protected $pdftoppmPath;
    protected $languages;

    protected $minCharsPerPage;
    protected $dpi;
    protected $oem;
    protected $psm;
    protected $pageTimeout;
    protected $maxConcurrency;

    public function __construct()
    {
        $this->tesseractPath = config('services.ocr.tesseract_path');
        $this->pdftoppmPath  = config('services.ocr.pdftoppm_path');
        $this->languages     = config('services.ocr.languages', 'eng+fil');

        $this->minCharsPerPage = (int) config('ocr.min_text_chars_per_page', 100);
        $this->dpi             = (int) config('ocr.dpi', 300);
        $this->oem              = (int) config('ocr.oem', 1);
        $this->psm              = (int) config('ocr.psm', 3);
        $this->pageTimeout      = (int) config('ocr.page_timeout', 60);
        $this->maxConcurrency   = max(1, (int) config('ocr.max_concurrency', 2));
    }

    /**
     * Extract text page-by-page. Pages with a real text layer are returned
     * as-is; pages with too little text are rasterized and OCR'd.
     *
     * Returns:
     *   [
     *     'pages'      => [['page_number' => int, 'text' => string, 'ocr' => bool, 'confidence' => ?float], ...],
     *     'used_ocr'   => bool,
     *     'confidence' => ?float   mean OCR confidence across OCR'd pages (null if no page needed OCR),
     *   ]
     *
     * @throws \RuntimeException if OCR is needed but Tesseract/Poppler aren't callable.
     */
    public function extractDocument(string $pdfPath): array
    {
        $nativePages = $this->extractNativePages($pdfPath);

        if (empty($nativePages)) {
            // smalot/pdfparser couldn't read the file at all (corrupt, encrypted,
            // or genuinely 100% image-based with no parseable structure). We have
            // no way to know the page count without rendering, so every page is
            // rasterized here — this is the one case where we can't avoid it.
            $nativePages = $this->rasterizeWholeDocumentAsBlankPages($pdfPath);
        }

        $pagesNeedingOcr = [];
        foreach ($nativePages as $pageNum => $text) {
            if ($this->charDensity($text) < $this->minCharsPerPage) {
                $pagesNeedingOcr[] = $pageNum;
            }
        }

        $ocrResults = [];
        $usedOcr    = false;

        if (!empty($pagesNeedingOcr)) {
            if (!$this->isOcrAvailable()) {
                throw new \RuntimeException(
                    'This document has ' . count($pagesNeedingOcr) . ' page(s) with no readable text layer, '
                    . 'but OCR tools (Tesseract/Poppler) are not installed or not callable on this server.'
                );
            }

            if (stripos($this->languages, 'fil') !== false && !$this->isFilipinoLanguageAvailable()) {
                throw new \RuntimeException(
                    "OCR is configured to use the Filipino language pack ('fil') but it is not installed on this "
                    . "server's Tesseract. Install it and retry — on Windows (UB-Mannheim build), re-run the "
                    . "installer and check the Filipino component, or download 'fil.traineddata' from "
                    . "https://github.com/tesseract-ocr/tessdata and place it in the tessdata directory "
                    . '(alongside eng.traineddata). On Linux: apt-get install tesseract-ocr-fil.'
                );
            }

            $ocrResults = $this->ocrPages($pdfPath, $pagesNeedingOcr);
            $usedOcr    = true;
        }

        $pages       = [];
        $confidences = [];

        foreach ($nativePages as $pageNum => $text) {
            if (isset($ocrResults[$pageNum])) {
                $conf = $ocrResults[$pageNum]['confidence'];
                if ($conf !== null) {
                    $confidences[] = $conf;
                }
                $pages[] = [
                    'page_number' => $pageNum,
                    'text'        => $ocrResults[$pageNum]['text'],
                    'ocr'         => true,
                    'confidence'  => $conf,
                ];
            } else {
                $pages[] = [
                    'page_number' => $pageNum,
                    'text'        => $text,
                    'ocr'         => false,
                    'confidence'  => null,
                ];
            }
        }

        return [
            'pages'      => $pages,
            'used_ocr'   => $usedOcr,
            'confidence' => $confidences ? array_sum($confidences) / count($confidences) : null,
        ];
    }

    /**
     * Return true if OCR tools are installed and callable.
     */
    public function isOcrAvailable(): bool
    {
        $tesseract = $this->runCommand([$this->tesseractPath, '--version'], 10);
        $pdftoppm  = $this->runCommand([$this->pdftoppmPath, '-v'], 10);

        return stripos($tesseract ?? '', 'tesseract') !== false
            && stripos($pdftoppm ?? '', 'pdftoppm') !== false;
    }

    /**
     * Return true if the 'fil' (Filipino) Tesseract language pack is installed.
     * Tesseract prints its available languages via --list-langs (to stderr).
     */
    public function isFilipinoLanguageAvailable(): bool
    {
        $output = $this->runCommand([$this->tesseractPath, '--list-langs'], 10) ?? '';
        return stripos($output, 'fil') !== false;
    }

    // ── Stage 1: pdfparser (per page) ──────────────────────────────────────────

    /**
     * @return array<int, string> 1-indexed page number => page text. Empty
     *   array means the file could not be parsed at all.
     */
    private function extractNativePages(string $pdfPath): array
    {
        try {
            $document = (new \Smalot\PdfParser\Parser())->parseFile($pdfPath);
            $pages    = [];

            foreach ($document->getPages() as $i => $page) {
                $pages[$i + 1] = $page->getText() ?? '';
            }

            return $pages;
        } catch (\Throwable $e) {
            Log::warning('[OcrService] pdfparser threw: ' . $e->getMessage());
            return [];
        }
    }

    private function charDensity(string $text): int
    {
        return strlen(preg_replace('/\s+/', '', $text));
    }

    /**
     * Fallback for files smalot/pdfparser can't read at all: rasterize the
     * whole document just to discover the page count, treating every page as
     * empty native text so all of them get routed to OCR.
     */
    private function rasterizeWholeDocumentAsBlankPages(string $pdfPath): array
    {
        $tempDir = $this->makeTempDir();

        try {
            $prefix = $tempDir . '/probe';
            $this->runCommand([
                $this->pdftoppmPath, '-r', (string) $this->dpi, '-gray',
                $this->normalizePath($pdfPath), $this->normalizePath($prefix),
            ]);

            $images = $this->sortedPageImages($tempDir);

            if (empty($images)) {
                Log::error('[OcrService] Could not rasterize document to discover page count: ' . $pdfPath);
                return [1 => ''];
            }

            $pages = [];
            foreach (array_keys($images) as $pageNum) {
                $pages[$pageNum] = '';
            }
            return $pages;
        } finally {
            $this->cleanupDir($tempDir);
        }
    }

    // ── Stage 2: pdftoppm + tesseract (only for pages that need it) ────────────

    /**
     * @param int[] $pageNumbers 1-indexed page numbers to OCR.
     * @return array<int, array{text:string, confidence:?float}>
     */
    private function ocrPages(string $pdfPath, array $pageNumbers): array
    {
        $tempDir = $this->makeTempDir();

        try {
            $imagesByPage = [];
            foreach ($pageNumbers as $pageNum) {
                $imagePath = $this->rasterizePage($pdfPath, $pageNum, $tempDir);
                if ($imagePath !== null) {
                    $imagesByPage[$pageNum] = $imagePath;
                } else {
                    Log::error("[OcrService] Failed to rasterize page {$pageNum}");
                }
            }

            return $this->ocrImagesConcurrently($imagesByPage);
        } finally {
            $this->cleanupDir($tempDir);
        }
    }

    /**
     * Rasterize a single page to a grayscale image (grayscale is smaller and
     * slightly faster for Tesseract than full color, and Poppler supports it
     * natively — no extra dependency needed).
     */
    private function rasterizePage(string $pdfPath, int $pageNum, string $tempDir): ?string
    {
        $prefix = $tempDir . '/page';

        $this->runCommand([
            $this->pdftoppmPath,
            '-r', (string) $this->dpi,
            '-f', (string) $pageNum,
            '-l', (string) $pageNum,
            '-gray',
            $this->normalizePath($pdfPath),
            $this->normalizePath($prefix),
        ]);

        // pdftoppm names output using the real page number, e.g. page-7.pgm
        $candidates = glob($this->normalizePath($tempDir) . '/*-' . $pageNum . '.pgm')
            ?: glob($this->normalizePath($tempDir) . '/*-' . $pageNum . '.ppm')
            ?: [];

        return $candidates[0] ?? null;
    }

    /**
     * Run Tesseract on each page image, up to `max_concurrency` processes at
     * once, each bounded by `page_timeout`. A page that fails or times out is
     * recorded as empty/unconfident rather than aborting the whole document.
     *
     * @param array<int, string> $imagesByPage page_number => image path
     * @return array<int, array{text:string, confidence:?float}>
     */
    private function ocrImagesConcurrently(array $imagesByPage): array
    {
        $pending = $imagesByPage;
        $running = []; // page_number => Process
        $results = [];

        while (!empty($pending) || !empty($running)) {
            while (count($running) < $this->maxConcurrency && !empty($pending)) {
                $pageNum   = array_key_first($pending);
                $imagePath = $pending[$pageNum];
                unset($pending[$pageNum]);

                $process = new Process([
                    $this->tesseractPath,
                    $imagePath,
                    'stdout',
                    '--oem', (string) $this->oem,
                    '--psm', (string) $this->psm,
                    '-l', $this->languages,
                    'tsv',
                ]);
                $process->setTimeout($this->pageTimeout);

                try {
                    $process->start();
                    $running[$pageNum] = $process;
                } catch (\Throwable $e) {
                    Log::error("[OcrService] Failed to start tesseract for page {$pageNum}: " . $e->getMessage());
                    $results[$pageNum] = ['text' => '', 'confidence' => null];
                }
            }

            usleep(50000);

            foreach ($running as $pageNum => $process) {
                try {
                    if ($process->isRunning()) {
                        continue;
                    }
                } catch (ProcessTimedOutException $e) {
                    Log::warning("[OcrService] tesseract timed out on page {$pageNum} after {$this->pageTimeout}s");
                    unset($running[$pageNum]);
                    $results[$pageNum] = ['text' => '', 'confidence' => null];
                    continue;
                }

                unset($running[$pageNum]);

                if ($process->isSuccessful()) {
                    $results[$pageNum] = $this->parseTsv($process->getOutput());
                } else {
                    Log::warning("[OcrService] tesseract failed on page {$pageNum}", [
                        'error' => trim($process->getErrorOutput()),
                    ]);
                    $results[$pageNum] = ['text' => '', 'confidence' => null];
                }
            }
        }

        ksort($results);
        return $results;
    }

    /**
     * Parse Tesseract's TSV output into reconstructed page text plus a mean
     * word-confidence score. Reconstructing text from TSV (rather than a
     * second plain-text invocation) keeps OCR to one process per page.
     */
    private function parseTsv(string $tsv): array
    {
        $lines = explode("\n", trim($tsv));
        array_shift($lines); // header row

        $textParts   = [];
        $confidences = [];
        $lastLineKey = null;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $cols = explode("\t", $line);
            if (count($cols) < 12) {
                continue; // stray stderr/log noise, not a real TSV row
            }

            // level page block par line word left top width height conf text
            $level = (int) $cols[0];
            if ($level !== 5) {
                continue; // only word-level rows carry recognized text
            }

            $text = trim($cols[11]);
            if ($text === '') {
                continue;
            }

            $lineKey = $cols[2] . '-' . $cols[3] . '-' . $cols[4];
            if ($lastLineKey !== null) {
                $textParts[] = ($lineKey !== $lastLineKey) ? "\n" : ' ';
            }
            $textParts[] = $text;
            $lastLineKey = $lineKey;

            $conf = (float) $cols[10];
            if ($conf >= 0) {
                $confidences[] = $conf;
            }
        }

        return [
            'text'       => trim(implode('', $textParts)),
            'confidence' => $confidences ? array_sum($confidences) / count($confidences) : null,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeTempDir(): string
    {
        $dir = $this->normalizePath(sys_get_temp_dir()) . '/ocr_' . uniqid('', true);
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create OCR temp dir: {$dir}");
        }
        return $dir;
    }

    /** @return array<int, string> page_number => image path, sorted by page number */
    private function sortedPageImages(string $tempDir): array
    {
        $files = glob($this->normalizePath($tempDir) . '/*.pgm') ?: glob($this->normalizePath($tempDir) . '/*.ppm') ?: [];
        $pages = [];

        foreach ($files as $file) {
            if (preg_match('/-(\d+)\.(pgm|ppm)$/', $file, $m)) {
                $pages[(int) $m[1]] = $file;
            }
        }

        ksort($pages);
        return $pages;
    }

    /**
     * Run a shell command safely using Symfony Process (array form — each
     * element is escaped as its own argument, no manual escapeshellarg needed
     * and no risk of shell injection via string concatenation).
     */
    private function runCommand(array $command, ?int $timeout = 120): ?string
    {
        try {
            $process = new Process($command);
            $process->setTimeout($timeout);
            $process->run();

            $output = trim($process->getOutput() . "\n" . $process->getErrorOutput());

            if (!$process->isSuccessful()) {
                Log::warning('[OcrService] Process returned non-zero exit code.', [
                    'command' => $process->getCommandLine(),
                    'output'  => $output,
                ]);
            }

            return $output;
        } catch (\Throwable $e) {
            Log::error('[OcrService] Process execution failed', [
                'command' => implode(' ', $command),
                'error'   => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Convert backslash paths to forward slashes.
     * PHP's glob(), file_exists(), and shell commands all work correctly
     * with forward slashes on Windows, but backslashes cause intermittent
     * failures especially inside glob() patterns.
     */
    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private function cleanupDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($this->normalizePath($dir) . '/*') ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }
}
