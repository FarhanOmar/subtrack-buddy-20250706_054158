<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\SubscriptionReminderMail;
use App\Jobs\SendWhatsAppReminderJob;

class ReminderService
{
    protected array $reminderIntervals;

    public function __construct(array $reminderIntervals = null)
    {
        $this->reminderIntervals = $reminderIntervals ?? config('subscriptions.reminder_intervals', [30, 7, 1]);
    }

    public function scheduleReminders(): void
    {
        foreach ($this->reminderIntervals as $days) {
            $targetDate = Carbon::now()->addDays($days);
            $startOfDay = $targetDate->copy()->startOfDay();
            $endOfDay = $targetDate->copy()->endOfDay();

            $subscriptions = Subscription::with(['user', 'team.members'])
                ->where('is_active', true)
                ->whereBetween('ends_at', [$startOfDay, $endOfDay])
                ->get();

            foreach ($subscriptions as $subscription) {
                $this->sendEmailReminder($subscription, $days);
                $this->sendWhatsAppReminder($subscription, $days);
            }
        }
    }

    protected function sendEmailReminder(Subscription $subscription, int $days): void
    {
        $user = $subscription->user;
        if (! $user || ! $user->email) {
            Log::warning("Email reminder skipped: missing user or email for subscription ID {$subscription->id}");
            return;
        }

        try {
            Mail::to($user->email)
                ->queue(new SubscriptionReminderMail($subscription, $days));
        } catch (\Exception $e) {
            Log::error("Failed to send email reminder for subscription ID {$subscription->id}: {$e->getMessage()}");
        }
    }

    protected function sendWhatsAppReminder(Subscription $subscription, int $days): void
    {
        SendWhatsAppReminderJob::dispatch($subscription, $days)
            ->onQueue('whatsapp-reminders');
    }
}