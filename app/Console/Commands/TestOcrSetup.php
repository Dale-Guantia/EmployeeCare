<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OcrService;

/**
 * Diagnostic command: verifies OCR tools are installed and the pipeline works.
 *
 * Usage:
 *   php artisan hr:test-ocr
 *   php artisan hr:test-ocr --pdf="C:/xampp/htdocs/EmployeeCare/storage/app/policies/sample.pdf"
 */
class TestOcrSetup extends Command
{
    protected $signature = 'hr:test-ocr
        {--pdf= : Absolute path to a PDF file to test extraction on}';

    protected $description = 'Verify Tesseract and pdftoppm are installed and working';

    protected $ocr;

    public function __construct(OcrService $ocr)
    {
        parent::__construct();
        $this->ocr = $ocr;
    }

    public function handle(): int
    {
        $isWindows = PHP_OS_FAMILY === 'Windows';
        $allPassed = true;

        $this->info('=== OCR Setup Diagnostic ===');
        $this->line('Platform: ' . ($isWindows ? 'Windows' : PHP_OS_FAMILY));
        $this->newLine();

        // ── Check 1: Tesseract ────────────────────────────────────────────────
        $this->line('Checking Tesseract...');
        $tesseractPath = config('services.ocr.tesseract_path', 'tesseract');
        $tsOutput      = shell_exec(escapeshellarg($tesseractPath) . ' --version 2>&1');

        if ($tsOutput && stripos($tsOutput, 'tesseract') !== false) {
            // Version is on the FIRST line: "tesseract 5.3.4" or "tesseract v5.3.4"
            $firstLine = strtok($tsOutput, "\n");
            // Match both "tesseract 5.3.4" and "tesseract v5.3.4"
            preg_match('/tesseract\s+v?([\d.]+)/i', $firstLine, $m);
            $version = $m[1] ?? trim($firstLine);
            $this->info('  ✓ Tesseract found: version ' . $version);
        } else {
            $this->error('  ✗ Tesseract NOT found at: ' . $tesseractPath);
            $this->line('    Download: https://github.com/UB-Mannheim/tesseract/wiki');
            $this->line('    After install, add its folder to Windows PATH and restart XAMPP.');
            $allPassed = false;
        }

        // ── Check 2: Filipino language pack ──────────────────────────────────
        $this->line('Checking Filipino language pack...');
        $langs = shell_exec(escapeshellarg($tesseractPath) . ' --list-langs 2>&1');

        if ($langs && stripos($langs, 'fil') !== false) {
            $this->info('  ✓ Filipino (fil) language pack installed');
        } else {
            $this->warn('  ⚠ Filipino (fil) language pack NOT found');
            $this->line('    Windows: Re-run Tesseract installer → check "Filipino" under Additional Languages');
            $this->line('    Linux:   sudo apt install tesseract-ocr-fil');
            $this->line('    Will continue with English-only OCR (still works for most documents)');
        }

        // ── Check 3: pdftoppm ─────────────────────────────────────────────────
        $this->newLine();
        $this->line('Checking pdftoppm (Poppler)...');
        $pdftoppmPath = config('services.ocr.pdftoppm_path', 'pdftoppm');
        $ppOutput     = shell_exec(escapeshellarg($pdftoppmPath) . ' -v 2>&1');

        if ($ppOutput && stripos($ppOutput, 'pdftoppm') !== false) {
            preg_match('/version\s+([\d.]+)/i', $ppOutput, $m);
            $this->info('  ✓ pdftoppm found: version ' . ($m[1] ?? 'unknown'));
        } else {
            $this->error('  ✗ pdftoppm NOT found at: ' . $pdftoppmPath);
            $this->line('    Download: https://github.com/oschwartz10612/poppler-windows/releases');
            $this->line('    Extract to C:\poppler\ then add C:\poppler\Library\bin to Windows PATH');
            $this->line('    Restart XAMPP after updating PATH.');
            $allPassed = false;
        }

        // ── Check 4: shell_exec enabled ──────────────────────────────────────
        $this->newLine();
        $this->line('Checking shell_exec...');
        $test = shell_exec('echo hello' . ($isWindows ? '' : ' 2>&1'));

        if (trim($test ?? '') === 'hello') {
            $this->info('  ✓ shell_exec is enabled');
        } else {
            $this->error('  ✗ shell_exec is DISABLED in PHP');
            $this->line('    In php.ini, find disable_functions and remove shell_exec from the list');
            $this->line('    php.ini location: ' . php_ini_loaded_file());
            $allPassed = false;
        }

        // ── Check 5: Temp directory ───────────────────────────────────────────
        $this->newLine();
        $this->line('Checking temp directory...');
        $tmpDir = sys_get_temp_dir();

        if (is_writable($tmpDir)) {
            $this->info('  ✓ Temp directory writable: ' . $tmpDir);
        } else {
            $this->error('  ✗ Temp directory NOT writable: ' . $tmpDir);
            $allPassed = false;
        }

        // ── Check 6: PATH sanity check on Windows ────────────────────────────
        if ($isWindows) {
            $this->newLine();
            $this->line('Checking Windows PATH...');
            $pathOutput = shell_exec('echo %PATH% 2>&1');

            $tesseractInPath = $pathOutput && stripos($pathOutput, 'Tesseract') !== false;
            $popplerInPath   = $pathOutput && stripos($pathOutput, 'poppler') !== false;

            if ($tesseractInPath) {
                $this->info('  ✓ Tesseract folder found in PATH');
            } else {
                $this->warn('  ⚠ Tesseract folder not detected in PATH');
                $this->line('    If Tesseract is installed but not in PATH, set in .env:');
                $this->line('    OCR_TESSERACT_PATH="C:/Program Files/Tesseract-OCR/tesseract.exe"');
            }

            if ($popplerInPath) {
                $this->info('  ✓ Poppler folder found in PATH');
            } else {
                $this->warn('  ⚠ Poppler folder not detected in PATH');
                $this->line('    If Poppler is installed but not in PATH, set in .env:');
                $this->line('    OCR_PDFTOPPM_PATH="C:/poppler/Library/bin/pdftoppm.exe"');
            }
        }

        // ── Check 7: Full pipeline test ───────────────────────────────────────
        $this->newLine();
        $pdfPath = $this->option('pdf');

        if ($pdfPath) {
            // Normalize slashes for cross-platform safety
            $pdfPath = str_replace('\\', '/', $pdfPath);

            $this->line('Running full pipeline test on: ' . $pdfPath);

            if (!file_exists($pdfPath)) {
                $this->error('  ✗ PDF file not found: ' . $pdfPath);
                $allPassed = false;
            } else {
                $isScanned = $this->ocr->isScannedPdf($pdfPath);
                $this->line('  PDF type: ' . ($isScanned ? 'Scanned image (OCR needed)' : 'Text-based (pdfparser)'));

                $this->line('  Extracting text...');
                $start     = microtime(true);
                $text      = $this->ocr->extractText($pdfPath);
                $elapsed   = round(microtime(true) - $start, 2);
                $wordCount = str_word_count($text);

                if ($wordCount >= 50) {
                    $this->info("  ✓ Extracted {$wordCount} words in {$elapsed}s");
                    $preview = substr(preg_replace('/\s+/', ' ', $text), 0, 300);
                    $this->line('');
                    $this->line('  Preview:');
                    $this->line('  ' . $preview . '...');
                } else {
                    $this->error("  ✗ Only extracted {$wordCount} words — pipeline still failing");
                    $this->newLine();
                    $this->line('  === Debug: running pipeline steps manually ===');

                    // Step-by-step debug
                    $tmpDir     = str_replace('\\', '/', sys_get_temp_dir()) . '/ocr_debug_' . time();
                    mkdir($tmpDir, 0755, true);
                    $imgPrefix  = $tmpDir . '/page';

                    $ppCmd = sprintf(
                        '%s -r 200 %s %s 2>&1',
                        escapeshellarg(config('services.ocr.pdftoppm_path', 'pdftoppm')),
                        escapeshellarg($pdfPath),
                        escapeshellarg($imgPrefix)
                    );
                    $this->line('  pdftoppm command: ' . $ppCmd);
                    $ppOut = shell_exec($ppCmd);
                    $this->line('  pdftoppm output: ' . ($ppOut ?: '(none)'));

                    $produced = glob($tmpDir . '/*.ppm') ?: [];
                    $this->line('  Images produced: ' . count($produced));

                    if (!empty($produced)) {
                        $firstImage = reset($produced);
                        $this->line('  First image: ' . $firstImage . ' (' . round(filesize($firstImage) / 1024) . ' KB)');

                        $tsCmd = sprintf(
                            '%s %s stdout --oem 1 --psm 3 -l %s 2>&1',
                            escapeshellarg(config('services.ocr.tesseract_path', 'tesseract')),
                            escapeshellarg($firstImage),
                            escapeshellarg(config('services.ocr.languages', 'eng+fil'))
                        );
                        $this->line('  tesseract command: ' . $tsCmd);
                        $tsOut = shell_exec($tsCmd);
                        $this->line('  tesseract output (' . str_word_count($tsOut ?? '') . ' words):');
                        $this->line('  ' . substr($tsOut ?? '(none)', 0, 400));
                    }

                    // Cleanup debug files
                    foreach (glob($tmpDir . '/*') ?: [] as $f) @unlink($f);
                    @rmdir($tmpDir);

                    $allPassed = false;
                }
            }
        } else {
            $this->line('Tip: run with --pdf="path/to/scanned.pdf" to test a real document.');
        }

        // ── Summary ───────────────────────────────────────────────────────────
        $this->newLine();
        $this->line('=== Result ===');

        if ($allPassed) {
            $this->info('All checks passed! OCR is ready.');
            $this->line('Scanned PDFs uploaded via Policy Documents will be automatically processed.');
        } else {
            $this->error('Some checks failed. See the messages above for how to fix each one.');
            $this->line('Text-based PDFs (downloaded from websites) still work without OCR tools.');
        }

        return $allPassed ? 0 : 1;
    }
}
