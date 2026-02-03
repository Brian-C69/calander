<?php

namespace App\Console\Commands;

use App\Models\EventNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchEventNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:dispatch-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch pending event reminders (stub: logs instead of sending)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now();

        $pending = EventNotification::with(['user:id,email,name', 'event:id,title,start_at'])
            ->where('status', 'pending')
            ->where('send_at', '<=', $now)
            ->orderBy('send_at')
            ->limit(50)
            ->get();

        foreach ($pending as $notification) {
            // Stub: log instead of sending email/push.
            Log::info('Dispatching event reminder', [
                'user_id' => $notification->user_id,
                'event_id' => $notification->event_id,
                'title' => $notification->event?->title,
                'send_at' => $notification->send_at,
            ]);

            $notification->update(['status' => 'sent']);
        }

        $this->info("Processed {$pending->count()} reminders");

        return Command::SUCCESS;
    }
}
