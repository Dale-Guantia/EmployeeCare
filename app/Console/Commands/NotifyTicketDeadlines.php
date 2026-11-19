<?php

namespace App\Console\Commands;

use App\Services\TicketDeadlineNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class NotifyTicketDeadlines extends Command
{
    protected $signature = 'tickets:notify-deadlines';
    protected $description = 'Notify eligible ticket participants before and upon the 72-hour deadline';

    public function handle(TicketDeadlineNotifier $notifier): int
    {
        if (!Schema::hasTable('ticket_deadline_notifications')) {
            $this->error('Apply the ticket deadline notification migration first.');
            return self::FAILURE;
        }
        $this->info('Deadline notifications sent: ' . $notifier->send());
        return self::SUCCESS;
    }
}
