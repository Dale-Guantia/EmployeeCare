<?php

namespace App\Listeners;

class UpdateLastLoginAt
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        try {
            $event->user->update([
                'last_login_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error(
                '[UpdateLastLoginAt] Failed to update last_login_at for user ' . optional($event->user)->id . ': ' . $e->getMessage()
            );
            // Never let this non-critical side effect block a successful login.
        }
    }
}
