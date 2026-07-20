<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BackfillHrConversationsFromLogs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Idempotent: only touches logs that don't already have a conversation.
        // Safe to re-run (e.g. after a partial failure) without duplicating data.
        $orphanCount = DB::table('hr_chat_logs')->whereNull('conversation_id')->count();
        if ($orphanCount === 0) {
            echo "  Backfill: no orphaned hr_chat_logs found, nothing to do.\n";
            return;
        }

        $hasSessionId = Schema::hasColumn('hr_chat_logs', 'session_id');
        $conversationsCreated = 0;
        $logsReassigned = 0;

        DB::transaction(function () use ($hasSessionId, &$conversationsCreated, &$logsReassigned) {
            $userIds = DB::table('hr_chat_logs')
                ->whereNull('conversation_id')
                ->distinct()
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                if ($userId === null) {
                    continue;
                }

                $logs = DB::table('hr_chat_logs')
                    ->where('user_id', $userId)
                    ->whereNull('conversation_id')
                    ->orderBy('id')
                    ->get(['id', 'question', 'created_at', $hasSessionId ? 'session_id' : DB::raw('NULL as session_id')]);

                // Group by session_id when it exists (one conversation per prior
                // browser session); logs with no session_id (or when the column
                // doesn't exist at all) fall into a single "Imported history"
                // bucket per user so nothing is lost.
                $groups = [];
                foreach ($logs as $log) {
                    $key = $hasSessionId && $log->session_id ? $log->session_id : '__imported__';
                    $groups[$key][] = $log;
                }

                foreach ($groups as $key => $groupLogs) {
                    $first = $groupLogs[0];
                    $last  = end($groupLogs);

                    $title = $key === '__imported__'
                        ? 'Imported history'
                        : (Str::limit(trim(preg_replace('/\s+/', ' ', $first->question)), 60, '…') ?: 'Imported history');

                    $conversationId = DB::table('hr_conversations')->insertGetId([
                        'user_id'         => $userId,
                        'title'           => $title,
                        'is_starred'      => false,
                        'last_message_at' => $last->created_at,
                        'created_at'      => $first->created_at,
                        'updated_at'      => $last->created_at,
                    ]);
                    $conversationsCreated++;

                    $logIds = array_map(function ($log) { return $log->id; }, $groupLogs);
                    DB::table('hr_chat_logs')
                        ->whereIn('id', $logIds)
                        ->update(['conversation_id' => $conversationId]);
                    $logsReassigned += count($logIds);
                }
            }
        });

        $remainingOrphans = DB::table('hr_chat_logs')->whereNull('conversation_id')->count();

        echo "  Backfill: created {$conversationsCreated} conversation(s), reassigned {$logsReassigned} log(s), {$remainingOrphans} orphan(s) remaining.\n";
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Data-only migration: nothing structural to reverse. Leaving
        // conversation_id populated on down() is intentional — clearing it
        // would silently orphan logs again with no way to recover the grouping.
    }
}
