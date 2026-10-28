<?php

namespace Tests\Support;

use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

class EngineSubmissionRace
{
    public static function run(string $kind, array $payloads, string $token, string $mode = 'isolated'): array
    {
        $processes = []; $inputs = []; $output = ['', ''];
        try {
            foreach ($payloads as $i => $payload) {
                $inputs[$i] = new InputStream;
                $processes[$i] = new Process([PHP_BINARY, dirname(__DIR__) . '/Engine/submission-worker.php',
                    $kind, json_encode($payload), $token, $mode], base_path(), null, $inputs[$i], 30);
                $processes[$i]->start();
            }
            $deadline = microtime(true) + 20;
            while (!preg_match('/^READY\r?$/m', $output[0]) || !preg_match('/^READY\r?$/m', $output[1])) {
                foreach ($processes as $i => $process) {
                    $output[$i] .= $process->getIncrementalOutput();
                    if (!$process->isRunning() || microtime(true) > $deadline) {
                        throw new \RuntimeException('Engine race workers did not reach the concurrent SELECT barrier; connection output suppressed.');
                    }
                }
                usleep(10000);
            }
            foreach ($inputs as $input) { $input->write("GO\n"); $input->close(); }
            // Pump both input pipes before waiting for either result, so both INSERTs can compete.
            do {
                $running = false;
                foreach ($processes as $i => $process) {
                    $output[$i] .= $process->getIncrementalOutput();
                    $process->checkTimeout();
                    $running = $process->isRunning() || $running;
                }
                if ($running) { usleep(10000); }
            } while ($running);
            $results = [];
            foreach ($processes as $i => $process) {
                $output[$i] .= $process->getIncrementalOutput();
                if ($process->getExitCode() !== 0 || !preg_match('/RESULT ([^\r\n]+)/', $output[$i], $match)) {
                    throw new \RuntimeException('Engine worker failed; output suppressed to protect connection details.');
                }
                $results[] = json_decode($match[1], true);
            }
            return $results;
        } finally {
            foreach ($processes as $process) { if ($process->isRunning()) { $process->stop(0); } }
        }
    }
}
