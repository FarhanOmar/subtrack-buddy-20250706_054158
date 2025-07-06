public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Subscription::class);

        $user = $request->user();
        $currentTeam = $user->currentTeam; // assumes currentTeam relationship or accessor

        $query = Subscription::with(['team', 'user'])
            ->when($currentTeam, function ($q) use ($currentTeam) {
                return $q->where('team_id', $currentTeam->id);
            }, function ($q) use ($user) {
                return $q->where('user_id', $user->id);
            })
            ->orderBy('renewal_date', 'asc');

        $subscriptions = $query->paginate(15);

        return view('subscriptions.index', compact('subscriptions'));
    }

    public function show(Subscription $subscription)
    {
        $this->authorize('view', $subscription);

        return view('subscriptions.show', compact('subscription'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Subscription::class);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'provider' => 'nullable|string|max:255',
            'cost' => 'nullable|numeric',
            'renewal_date' => 'nullable|date',
            'status' => 'nullable|string|in:active,pending,expired,cancelled',
        ]);

        $user = $request->user();
        $currentTeam = $user->currentTeam;

        if ($currentTeam) {
            $data['team_id'] = $currentTeam->id;
        } else {
            $data['user_id'] = $user->id;
        }

        Subscription::create($data);

        return redirect()->route('subscriptions.index')->with('success', 'Subscription created successfully.');
    }

    public function update(Request $request, Subscription $subscription)
    {
        $this->authorize('update', $subscription);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'provider' => 'nullable|string|max:255',
            'cost' => 'nullable|numeric',
            'renewal_date' => 'nullable|date',
            'status' => 'nullable|string|in:active,pending,expired,cancelled',
        ]);

        $subscription->update($data);

        return redirect()->route('subscriptions.show', $subscription)->with('success', 'Subscription updated successfully.');
    }

    public function destroy(Subscription $subscription)
    {
        $this->authorize('delete', $subscription);

        $subscription->delete();

        return redirect()->route('subscriptions.index')->with('success', 'Subscription deleted successfully.');
    }
}