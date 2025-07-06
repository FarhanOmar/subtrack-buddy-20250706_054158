public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (UnexpectedValueException $e) {
            Log::error('Stripe Webhook: Invalid payload', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe Webhook: Invalid signature', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Idempotency: skip if event already processed
        $cacheKey = 'stripe_event_' . $event->id;
        if (! Cache::add($cacheKey, true, 60 * 60 * 24)) {
            Log::info('Duplicate Stripe Webhook event skipped', ['event_id' => $event->id]);
            return response()->json(['status' => 'duplicate'], 200);
        }

        $data = $event->data->object;

        switch ($event->type) {
            case 'customer.subscription.created':
            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($data);
                break;
            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($data);
                break;
            case 'invoice.payment_succeeded':
                $this->handleInvoicePaymentSucceeded($data);
                break;
            case 'invoice.payment_failed':
                $this->handleInvoicePaymentFailed($data);
                break;
            default:
                Log::info('Unhandled Stripe Webhook event', ['type' => $event->type, 'event_id' => $event->id]);
        }

        return response()->json(['status' => 'success'], 200);
    }

    protected function handleSubscriptionUpdated($data)
    {
        $subscription = Subscription::firstOrNew(['stripe_id' => $data->id]);

        if (! $subscription->exists) {
            $userId = $this->getUserIdByCustomer($data->customer);
            if (is_null($userId)) {
                Log::error('Stripe Webhook: User not found for customer', ['customer_id' => $data->customer, 'subscription_id' => $data->id]);
                return;
            }
            $subscription->user_id = $userId;
        }

        $subscription->name = $data->metadata->name ?? $subscription->name;
        $subscription->stripe_price = $data->plan->id;
        $subscription->quantity = $data->quantity;
        $subscription->status = $data->status;
        $subscription->current_period_start = Carbon::createFromTimestamp($data->current_period_start);
        $subscription->current_period_end = Carbon::createFromTimestamp($data->current_period_end);
        $subscription->save();

        Log::info('Stripe Webhook: Subscription updated', ['id' => $subscription->id, 'stripe_id' => $data->id]);
    }

    protected function handleSubscriptionDeleted($data)
    {
        $subscription = Subscription::where('stripe_id', $data->id)->first();

        if ($subscription) {
            $subscription->status = 'canceled';
            $subscription->ends_at = Carbon::now();
            $subscription->save();
            Log::info('Stripe Webhook: Subscription canceled', ['id' => $subscription->id, 'stripe_id' => $data->id]);
        } else {
            Log::warning('Stripe Webhook: Subscription not found for deletion', ['stripe_id' => $data->id]);
        }
    }

    protected function handleInvoicePaymentSucceeded($data)
    {
        $subscription = Subscription::where('stripe_id', $data->subscription)->first();

        if ($subscription) {
            if (isset($data->status_transitions->paid_at)) {
                $subscription->last_payment_at = Carbon::createFromTimestamp($data->status_transitions->paid_at);
                $subscription->save();
            }
            Log::info('Stripe Webhook: Invoice payment succeeded', [
                'subscription_id' => $subscription->id,
                'stripe_subscription' => $data->subscription,
                'invoice_id' => $data->id
            ]);
        } else {
            Log::warning('Stripe Webhook: Subscription not found for payment succeeded', ['stripe_subscription' => $data->subscription]);
        }
    }

    protected function handleInvoicePaymentFailed($data)
    {
        $subscription = Subscription::where('stripe_id', $data->subscription)->first();

        if ($subscription) {
            Log::warning('Stripe Webhook: Invoice payment failed', [
                'subscription_id' => $subscription->id,
                'stripe_subscription' => $data->subscription,
                'invoice_id' => $data->id
            ]);
        } else {
            Log::warning('Stripe Webhook: Subscription not found for payment failed', ['stripe_subscription' => $data->subscription]);
        }
    }

    protected function getUserIdByCustomer($customerId)
    {
        $user = User::where('stripe_customer_id', $customerId)->first();
        return $user ? $user->id : null;
    }
}