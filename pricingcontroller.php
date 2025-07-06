public function __construct()
    {
        $this->middleware('auth');
    }

    public function showPricing()
    {
        $user = Auth::user();
        $plans = Plan::orderBy('price')->get();
        $currentSubscription = $user->subscribed('default') ? $user->subscription('default') : null;
        $currentPlanId = $currentSubscription ? $currentSubscription->stripe_price_id : null;

        return view('pricing', [
            'plans' => $plans,
            'currentPlanId' => $currentPlanId,
        ]);
    }

    public function upgradePlan(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'payment_method' => 'nullable|string',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $user = Auth::user();

        try {
            if (! $user->subscribed('default')) {
                if (empty($request->payment_method)) {
                    return redirect()->route('pricing')->with('error', 'Payment method is required to start a subscription.');
                }
                $user->newSubscription('default', $plan->stripe_price_id)
                     ->create($request->payment_method);
            } else {
                $user->subscription('default')->swap($plan->stripe_price_id);
            }

            return redirect()->route('pricing')->with('success', 'Plan updated to ' . $plan->name . '.');
        } catch (\Exception $e) {
            return redirect()->route('pricing')->with('error', 'Error updating plan: ' . $e->getMessage());
        }
    }

    public function downgradePlan(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $user = Auth::user();

        if (! $user->subscribed('default')) {
            return redirect()->route('pricing')->with('error', 'No active subscription to downgrade.');
        }

        try {
            $user->subscription('default')->swap($plan->stripe_price_id);

            return redirect()->route('pricing')->with('success', 'Plan downgraded to ' . $plan->name . '.');
        } catch (\Exception $e) {
            return redirect()->route('pricing')->with('error', 'Error downgrading plan: ' . $e->getMessage());
        }
    }
}