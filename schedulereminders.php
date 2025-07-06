<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Models\Subscription;
use App\Services\WhatsAppService;
use App\Mail\SubscriptionRenewalReminder;

class ScheduleReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subtrack:schedulereminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send automated renewal reminders via email and WhatsApp for due subscriptions';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $daysBefore = config('subscriptions.reminder_days_before', 7);
        $thresholdDate = Carbon::now()->addDays($daysBefore)->startOfDay();

        Subscription::with(['user', 'team.members'])
            ->whereNull('reminder_sent_at')
            ->whereDate('next_renewal_date', '<=', $thresholdDate)
            ->chunkById(100, function ($subscriptions) {
                foreach ($subscriptions as $subscription) {
                    $id = $subscription->id;
                    $timestamp = Carbon::now();

                    try {
                        $updated = Subscription::where('id', $id)
                            ->whereNull('reminder_sent_at')
                            ->update(['reminder_sent_at' => $timestamp]);

                        if (!$updated) {
                            $this->info("Skipping subscription ID {$id}, already processed.");
                            continue;
                        }
                    } catch (\Exception $e) {
                        Log::error("Failed to mark reminder_sent_at for subscription ID {$id}", ['exception' => $e]);
                        $this->error("Error marking subscription ID {$id} as processed. Check logs.");
                        continue;
                    }

                    $recipients = collect();
                    if ($subscription->user) {
                        $recipients->push($subscription->user);
                    }
                    if ($subscription->team) {
                        $subscription->team->members->each(function ($member) use ($recipients) {
                            $recipients->push($member);
                        });
                    }
                    $recipients = $recipients->unique('id');

                    $emails = $recipients->pluck('email')->filter()->unique();
                    $phones = $recipients->pluck('phone')->filter()->unique();

                    try {
                        foreach ($emails as $email) {
                            Mail::to($email)->queue(new SubscriptionRenewalReminder($subscription));
                        }

                        $whatsAppService = app(WhatsAppService::class);
                        $message = view('notifications.whatsapp.subscription_reminder', [
                            'subscription' => $subscription,
                            'user' => $subscription->user,
                        ])->render();
                        foreach ($phones as $phone) {
                            $whatsAppService->send($phone, $message);
                        }

                        $this->info("Reminders sent for subscription ID {$id}.");
                    } catch (\Exception $e) {
                        Log::error("Failed to send reminders for subscription ID {$id}", ['exception' => $e]);
                        $this->error("Error sending reminders for subscription ID {$id}. Check logs.");
                    }
                }
            });

        return 0;
    }
}