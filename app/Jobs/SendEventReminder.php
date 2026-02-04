<?php

namespace App\Jobs;

use App\Models\EventNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendEventReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public EventNotification $notification)
    {
        //
    }

    public function handle(): void
    {
        // Stub: replace with Mail/Push as needed.
        Log::info('Sending event reminder (stub)', [
            'notification_id' => $this->notification->id,
            'user_id' => $this->notification->user_id,
            'event_id' => $this->notification->event_id,
            'payload' => $this->notification->payload,
        ]);

        $this->notification->update(['status' => 'sent']);
    }
}
