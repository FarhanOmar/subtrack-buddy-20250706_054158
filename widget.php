public function mount(int $limit = 5, int $daysAhead = 30): void
    {
        $this->limit = $limit;
        $this->daysAhead = $daysAhead;

        $user = Auth::user();
        if (!$user) {
            $this->subscriptions = [];
            return;
        }

        $now = Carbon::now();
        $end = $now->copy()->addDays($this->daysAhead);

        $this->subscriptions = Subscription::query()
            ->where('user_id', $user->id)
            ->whereBetween('next_renewal_date', [$now, $end])
            ->orderBy('next_renewal_date')
            ->limit($this->limit)
            ->get()
            ->toArray();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.widget', [
            'subscriptions' => $this->subscriptions,
        ]);
    }
}