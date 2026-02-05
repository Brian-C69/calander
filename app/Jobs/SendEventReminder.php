<?php

namespace App\Jobs;

use App\Models\EventNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class SendEventReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public EventNotification $notification)
    {
        //
    }

    public function handle(): void
    {
        // TODO: replace with real mail/push; currently logs and attempts Web Push if subscriptions exist.
        $user = $this->notification->user;

        $payload = $this->notification->payload ?? [];
        $title = $payload['title'] ?? 'Event reminder';
        $body = $payload['start_at'] ?? '';

        // Attempt Web Push if VAPID keys and subscriptions exist
        $vapidPublic = config('services.vapid.public_key');
        $vapidPrivate = config('services.vapid.private_key');

        $subscriptions = $user?->pushSubscriptions ?? collect();

        if ($vapidPublic && $vapidPrivate && $subscriptions->count()) {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('app.url'),
                    'publicKey' => $vapidPublic,
                    'privateKey' => $vapidPrivate,
                ],
            ]);

            foreach ($subscriptions as $sub) {
                $subscription = Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys' => [
                        'p256dh' => $sub->public_key,
                        'auth' => $sub->auth_token,
                    ],
                ]);
                $webPush->queueNotification($subscription, json_encode([
                    'title' => $title,
                    'body' => $body,
                    'url' => url('/calendar'),
                ]));
            }

            foreach ($webPush->flush() as $report) {
                if (!$report->isSuccess()) {
                    Log::warning('Web Push failed', [
                        'endpoint' => $report->getRequest()->getUri()->__toString(),
                        'reason' => $report->getReason(),
                    ]);
                }
            }
        } else {
            Log::info('Sending event reminder (log stub)', [
                'notification_id' => $this->notification->id,
                'user_id' => $this->notification->user_id,
                'event_id' => $this->notification->event_id,
                'payload' => $payload,
            ]);
        }

        $this->notification->update(['status' => 'sent']);
    }
}
