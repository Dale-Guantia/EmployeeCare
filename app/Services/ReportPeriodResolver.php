<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Resolves the `period`/`half`/`year`/`start`/`end` query params shared by
 * the Reports page and the Dashboard into a concrete date range. Extracted
 * from ReportsController so both pages apply the exact same "All Time /
 * This Month / Semestral / Custom" semantics instead of drifting apart.
 *
 * Returns [period, startDate, endDate, half, year, periodError].
 */
class ReportPeriodResolver
{
    public static function resolve(Request $request): array
    {
        $period = $request->query('period', 'month');
        $year = (int) $request->query('year', now()->year);

        if ($period === 'all') {
            return ['all', null, null, null, $year, null];
        }

        if ($period === 'half') {
            $half = $request->query('half');
            if (!in_array($half, ['h1', 'h2'], true)) {
                // Default to whichever half today falls in.
                $half = now()->month <= 6 ? 'h1' : 'h2';
            }

            if ($half === 'h1') {
                $start = Carbon::create($year, 1, 1)->startOfDay();
                $end = Carbon::create($year, 6, 30)->endOfDay();
            } else {
                $start = Carbon::create($year, 7, 1)->startOfDay();
                $end = Carbon::create($year, 12, 31)->endOfDay();
            }

            return ['half', $start, $end, $half, $year, null];
        }

        if ($period === 'custom') {
            $startInput = $request->query('start');
            $endInput = $request->query('end');
            $start = null;
            $end = null;
            $error = null;

            if (!$startInput || !$endInput) {
                $error = 'Please provide both a start and end date for a custom range.';
            } else {
                try {
                    $start = Carbon::parse($startInput)->startOfDay();
                    $end = Carbon::parse($endInput)->endOfDay();
                } catch (\Exception $e) {
                    $error = 'Those dates could not be understood.';
                }

                if ($start && $end && $end->lt($start)) {
                    $error = 'End date must be on or after the start date.';
                    $start = null;
                    $end = null;
                }
            }

            if ($start && $end) {
                return ['custom', $start, $end, null, $year, null];
            }

            // Validation failed: fall back to "This Month" rather than crash
            // or silently swap the dates, carrying the error message so the
            // page can display it.
            $error = ($error ?? 'Invalid custom range.') . ' Showing this month instead.';
            $start = now()->startOfMonth()->startOfDay();
            $end = now()->endOfDay();

            return ['month', $start, $end, null, $year, $error];
        }

        // "month" (default) — current calendar month to date.
        $start = now()->startOfMonth()->startOfDay();
        $end = now()->endOfDay();

        return ['month', $start, $end, null, $year, null];
    }
}
