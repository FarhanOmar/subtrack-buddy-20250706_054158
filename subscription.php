public function renew(): self
    {
        $this->paid = false;
        $this->paid_at = null;
        $this->snoozed_until = null;
        $this->status = 'active';

        $date = $this->next_due_date ?? Carbon::now();

        switch ($this->frequency) {
            case 'daily':
                $date = Carbon::parse($date)->addDay();
                break;
            case 'weekly':
                $date = Carbon::parse($date)->addWeek();
                break;
            case 'monthly':
                $date = Carbon::parse($date)->addMonth();
                break;
            case 'yearly':
                $date = Carbon::parse($date)->addYear();
                break;
            default:
                $date = Carbon::parse($date)->addMonth();
                break;
        }

        $this->next_due_date = $date;
        $this->save();

        return $this;
    }

    /**
     * Snooze reminders for a given number of days.
     *
     * @param int $days
     * @return $this
     */
    public function snooze(int $days): self
    {
        $this->snoozed_until = Carbon::now()->addDays($days);
        $this->save();

        return $this;
    }

    /**
     * Reschedule the next due date to a specific date.
     *
     * @param Carbon $date
     * @return $this
     */
    public function reschedule(Carbon $date): self
    {
        $this->next_due_date = $date;
        $this->save();

        return $this;
    }

    /**
     * Mark the subscription as paid.
     *
     * @return $this
     */
    public function markPaid(): self
    {
        $this->paid = true;
        $this->paid_at = Carbon::now();
        $this->save();

        return $this;
    }

    /**
     * Send reminders for due subscriptions.
     *
     * @return void
     */
    public static function reminders(): void
    {
        $thresholdDays = config('subscriptions.reminder_days', 3);
        $today = Carbon::today();

        $subscriptions = self::where('paid', false)
            ->where('status', 'active')
            ->where(function (Builder $query) use ($today) {
                $query->whereNull('snoozed_until')
                      ->orWhere('snoozed_until', '<=', $today);
            })
            ->whereBetween('next_due_date', [
                $today,
                $today->copy()->addDays($thresholdDays),
            ])
            ->with('user')
            ->get();

        foreach ($subscriptions as $subscription) {
            Notification::send(
                $subscription->user,
                new SubscriptionRenewalReminder($subscription)
            );
        }
    }

    /**
     * User relationship.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Team relationship.
     *
     * @return BelongsTo
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}