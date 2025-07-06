<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $subscriptions = Subscription::where('user_id', $request->user()->id)->get();
        if ($request->wantsJson()) {
            return response()->json($subscriptions);
        }
        return view('subscriptions.index', compact('subscriptions'));
    }

    public function show(Request $request, Subscription $subscription)
    {
        $this->authorize('view', $subscription);
        if ($request->wantsJson()) {
            return response()->json($subscription);
        }
        return view('subscriptions.show', compact('subscription'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'cost' => 'required|numeric',
            'currency' => 'required|string|size:3',
            'due_date' => 'required|date',
            'billing_cycle' => 'required|in:daily,weekly,monthly,yearly',
        ]);
        $data['user_id'] = $request->user()->id;
        $subscription = Subscription::create($data);
        if ($request->wantsJson()) {
            return response()->json($subscription, 201);
        }
        return redirect()->route('subscriptions.show', $subscription);
    }

    public function update(Request $request, Subscription $subscription)
    {
        $this->authorize('update', $subscription);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'cost' => 'sometimes|required|numeric',
            'currency' => 'sometimes|required|string|size:3',
            'due_date' => 'sometimes|required|date',
            'billing_cycle' => 'sometimes|required|in:daily,weekly,monthly,yearly',
        ]);
        $subscription->update($data);
        if ($request->wantsJson()) {
            return response()->json($subscription);
        }
        return redirect()->route('subscriptions.show', $subscription);
    }

    public function destroy(Request $request, Subscription $subscription)
    {
        $this->authorize('delete', $subscription);
        $subscription->delete();
        if ($request->wantsJson()) {
            return response()->json(null, 204);
        }
        return redirect()->route('subscriptions.index');
    }
}