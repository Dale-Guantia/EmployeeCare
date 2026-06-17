<?php
namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * OcrService — cross-platform PDF text extraction
 *
 * Two-stage pipeline:
 * Stage 1: smalot/pdfparser (fast, no shell, works on text-layer PDFs)
 * Stage 2: pdftoppm + tesseract (for scanned image PDFs)
 *
 * Windows XAMPP installation:
 * Tesseract: https://github.com/UB-Mannheim/tesseract/wiki
 * Poppler:   https://github.com/oschwartz10612/poppler-windows/releases
 * Both must be configured via OCR_TESSERACT_PATH / OCR_PDFTOPPM_PATH in .env
 */
class OcrService
{
    // Minimum words from pdfparser before falling back to OCR
    const MIN_TEXT_WORDS = 50;

    protected $tesseractPath;
    protected $pdftoppmPath;
    protected $languages;

    public function __construct()
    {
        $this->tesseractPath = config('services.ocr.tesseract_path');
        $this->pdftoppmPath  = config('services.ocr.pdftoppm_path');
        $this->languages     = config('services.ocr.languages', 'eng+fil');
    }

    /**
     * Extract text from a PDF file.
     * Tries pdfparser first. Falls back to OCR if text is insufficient.
     */
    public function extractText(string $pdfPath): string
    {
        $text = $this->extractWithPdfParser($pdfPath);

        if ($this->hasEnoughText($text)) {
            Log::info('[OcrService] pdfparser succeeded: ' . basename($pdfPath) . ' (' . str_word_count($text) . ' words)');
            return $text;
        }

        Log::info('[OcrService] pdfparser returned ' . str_word_count($text) . ' words — falling back to OCR: ' . basename($pdfPath));

        $ocrText = $this->extractWithOcr($pdfPath);

        if ($this->hasEnoughText($ocrText)) {
            Log::info('[OcrService] OCR succeeded: ' . basename($pdfPath) . ' (' . str_word_count($ocrText) . ' words)');
            return $ocrText;
        }

        Log::warning('[OcrService] Both stages returned insufficient text for: ' . basename($pdfPath));
        return $ocrText ?: $text;
    }

    /**
     * Return true if OCR tools are installed and callable.
     */
    public function isOcrAvailable(): bool
    {
        $tesseract = $this->runCommand([$this->tesseractPath, '--version']);
        $pdftoppm  = $this->runCommand([$this->pdftoppmPath, '-v']);

        return stripos($tesseract ?? '', 'tesseract') !== false
            && stripos($pdftoppm ?? '', 'pdftoppm') !== false;
    }

    /**
     * Return true if the PDF has no readable text layer (i.e. is a scanned image).
     */
    public function isScannedPdf(string $pdfPath): bool
    {
        return !$this->hasEnoughText($this->extractWithPdfParser($pdfPath));
    }

    // ── Stage 1: pdfparser ────────────────────────────────────────────────────

    private function extractWithPdfParser(string $pdfPath): string
    {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            return $parser->parseFile($pdfPath)->getText() ?? '';
        } catch (\Throwable $e) {
            Log::warning('[OcrService] pdfparser threw: ' . $e->getMessage());
            return '';
        }
    }

    // ── Stage 2: pdftoppm + tesseract ─────────────────────────────────────────

    private function extractWithOcr(string $pdfPath): string
    {
        $tempDir = $this->normalizePath(sys_get_temp_dir()) . '/ocr_' . uniqid('', true);

        if (!mkdir($tempDir, 0755, true)) {
            Log::error('[OcrService] Cannot create temp dir: ' . $tempDir);
            return '';
        }

        try {
            $imagePrefix = $tempDir . '/page';

            if (!$this->convertPdfToImages($pdfPath, $imagePrefix, $tempDir)) {
                return '';
            }

            // Collect all image files — pdftoppm names them page-1.ppm, page-2.ppm ...
            $images = glob($tempDir . '/*.ppm') ?: [];

            if (empty($images)) {
                $images = glob($tempDir . '/*.png') ?: [];
            }

            if (empty($images)) {
                Log::error('[OcrService] pdftoppm ran but produced no image files in: ' . $tempDir);
                return '';
            }

            // Sort numerically so pages are processed in order
            natsort($images);

            $pages = [];
            foreach ($images as $i => $imagePath) {
                $pageText = $this->ocrImage($imagePath, $i + 1);
                if (!empty(trim($pageText))) {
                    $pages[] = $pageText;
                }
            }

            return implode("\n\n", $pages);

        } finally {
            $this->cleanupDir($tempDir);
        }
    }

    private function convertPdfToImages(string $pdfPath, string $imagePrefix, string $tempDir): bool
    {
        // 200 DPI: good balance of accuracy vs file size for government documents
        $command = [
            $this->pdftoppmPath,
            '-r', '200',
            $this->normalizePath($pdfPath),
            $this->normalizePath($imagePrefix)
        ];

        $output = $this->runCommand($command);

        Log::info('[OcrService] pdftoppm command executed', ['command' => implode(' ', $command)]);
        Log::info('[OcrService] pdftoppm output: ' . ($output ?: '(none)'));

        // Confirm files were actually created
        $produced = glob($this->normalizePath($tempDir) . '/*.ppm') ?: [];

        if (empty($produced)) {
            Log::error('[OcrService] pdftoppm produced no output files.', [
                'cmd'    => implode(' ', $command),
                'output' => $output,
                'tmpdir' => $tempDir,
            ]);
            return false;
        }

        Log::info('[OcrService] pdftoppm produced ' . count($produced) . ' page image(s)');
        return true;
    }

    private function ocrImage(string $imagePath, int $pageNum): string
    {
        $command = [
            $this->tesseractPath,
            $this->normalizePath($imagePath),
            'stdout',
            '--oem', '1',
            '--psm', '3',
            '-l', $this->languages
        ];

        $raw = $this->runCommand($command);

        if ($raw === null || $raw === '') {
            Log::warning('[OcrService] tesseract returned empty output for page ' . $pageNum);
            return '';
        }

        // Strip tesseract diagnostic lines that appear in output
        $lines    = explode("\n", $raw);
        $filtered = array_filter($lines, function ($line) {
            $trimmed = trim($line);
            if ($trimmed === '') return false;

            // Common tesseract diagnostic patterns to exclude
            $diagnostics = [
                'Estimating resolution',
                'Warning:',
                'Error:',
                'Empty page',
                'Too few characters',
                'read_params_file',
            ];

            foreach ($diagnostics as $pattern) {
                if (stripos($trimmed, $pattern) === 0) return false;
            }

            return true;
        });

        $text = implode("\n", $filtered);

        // Clean common OCR artifacts
        $text = str_replace("\f", "\n", $text);          // form-feed chars
        $text = preg_replace('/\r\n|\r/', "\n", $text);  // normalize line endings
        $text = preg_replace('/\n{3,}/', "\n\n", $text); // collapse blank lines

        return trim($text);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Run a shell command safely using Symfony Process.
     * Escapes arguments automatically and captures both stdout and stderr.
     */
    private function runCommand(array $command): ?string
    {
        try {
            $process = new Process($command);

            // Allow up to 2 minutes for processing large/complex PDFs
            // Set to null to remove the timeout entirely for massive PDFs
            $process->setTimeout(null);
            $process->run();

            // Combine standard output and error output (mimicking 2>&1)
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

    private function hasEnoughText(string $text): bool
    {
        return str_word_count(trim($text)) >= self::MIN_TEXT_WORDS;
    }

    private function cleanupDir(string $dir): void
    {
        if (!is_dir($dir)) return;

        $files = glob($this->normalizePath($dir) . '/*') ?: [];
        foreach ($files as $file) {
            if (is_file($file)) @unlink($file);
        }
        @rmdir($dir);
    }
}
