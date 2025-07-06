public function mount()
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        $team = $user->currentTeam;

        $userSubs = $user->subscriptions()->with('service')->get();
        $teamSubs = $team ? $team->subscriptions()->with('service')->get() : collect();

        // Cache totals and aggregates
        $userTotal   = $userSubs->count();
        $teamTotal   = $teamSubs->count();
        $userActive  = $userSubs->filter(fn($sub) => $sub->isActive())->count();
        $teamActive  = $teamSubs->filter(fn($sub) => $sub->isActive())->count();
        $userPending = $userTotal - $userActive;
        $teamPending = $teamTotal - $teamActive;
        $userMonthly = $userSubs->sum('monthly_cost');
        $teamMonthly = $teamSubs->sum('monthly_cost');

        $this->userSubscriptions  = $userSubs;
        $this->teamSubscriptions  = $teamSubs;
        $this->totalSubscriptions = $userTotal + $teamTotal;

        $now       = Carbon::now();
        $threshold = $now->copy()->addDays(7);

        $expiring = $userSubs->merge($teamSubs)->filter(function ($sub) use ($now, $threshold) {
            $dueDate = $sub->next_due_date instanceof Carbon
                ? $sub->next_due_date
                : Carbon::parse($sub->next_due_date);
            return $dueDate->between($now, $threshold);
        });

        $this->expiringSoon = $expiring->values();

        $this->statistics = [
            'user' => [
                'total'   => $userTotal,
                'active'  => $userActive,
                'pending' => $userPending,
            ],
            'team' => [
                'total'   => $teamTotal,
                'active'  => $teamActive,
                'pending' => $teamPending,
            ],
            'monthly_spend' => [
                'user' => $userMonthly,
                'team' => $teamMonthly,
            ],
        ];
    }

    public function render(): View
    {
        return view('livewire.dashboard', [
            'userSubscriptions'  => $this->userSubscriptions,
            'teamSubscriptions'  => $this->teamSubscriptions,
            'totalSubscriptions' => $this->totalSubscriptions,
            'expiringSoon'       => $this->expiringSoon,
            'statistics'         => $this->statistics,
        ]);
    }
}