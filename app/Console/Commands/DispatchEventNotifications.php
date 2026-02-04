<?php

namespace App\Console\Commands;

use App\Models\EventNotification;
use App\Jobs\SendEventReminder;
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
            // Queue a reminder job (stub logs). Replace with Mail/Push as needed.
            SendEventReminder::dispatch($notification);
        }

        $this->info("Processed {$pending->count()} reminders");

        return Command::SUCCESS;
    }
}
