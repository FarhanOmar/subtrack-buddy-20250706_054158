public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
        $this->webhookSecret = config('services.stripe.webhook_secret');
    }

    public function createCheckoutSession(User $user, string $planId): string
    {
        try {
            if (empty($user->stripe_customer_id)) {
                $customer = $this->stripe->customers->create([
                    'email' => $user->email,
                    'metadata' => ['user_id' => $user->id],
                ]);
                $user->stripe_customer_id = $customer->id;
                $user->save();
            } else {
                $customer = $this->stripe->customers->retrieve($user->stripe_customer_id);
            }

            $session = $this->stripe->checkout->sessions->create([
                'customer' => $customer->id,
                'payment_method_types' => ['card'],
                'line_items' => [
                    ['price' => $planId, 'quantity' => 1],
                ],
                'mode' => 'subscription',
                'success_url' => route('billing.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('billing.cancel'),
            ]);

            return $session->url;
        } catch (ApiErrorException $e) {
            Log::error("Stripe API Error creating checkout session for user {$user->id}: {$e->getMessage()}");
            throw new \RuntimeException('Unable to create checkout session at this time.', 0, $e);
        }
    }

    public function handleWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
        } catch (UnexpectedValueException $e) {
            Log::error('Stripe webhook payload invalid: ' . $e->getMessage());
            return response('Invalid payload', 400);
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook signature verification failed: ' . $e->getMessage());
            return response('Invalid signature', 400);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;
                $customerId = $session->customer;
                $subscriptionId = $session->subscription;
                $user = User::where('stripe_customer_id', $customerId)->first();
                if ($user && $subscriptionId) {
                    Subscription::updateOrCreate(
                        ['stripe_subscription_id' => $subscriptionId],
                        [
                            'user_id' => $user->id,
                            'stripe_customer_id' => $customerId,
                            'status' => 'active',
                        ]
                    );
                }
                break;

            case 'invoice.payment_failed':
                $invoice = $event->data->object;
                $subscriptionId = $invoice->subscription;
                if (!empty($subscriptionId)) {
                    $this->updateCustomerSubscription($subscriptionId, 'past_due');
                }
                break;

            case 'customer.subscription.updated':
                $subscription = $event->data->object;
                $this->updateCustomerSubscription($subscription->id, $subscription->status);
                break;

            case 'customer.subscription.deleted':
                $subscription = $event->data->object;
                $this->updateCustomerSubscription($subscription->id, 'canceled');
                break;
        }

        return response('Webhook handled', 200);
    }

    public function updateCustomerSubscription(string $stripeSubscriptionId, string $status): void
    {
        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscriptionId)->first();
        if ($subscription) {
            $subscription->status = $status;
            $subscription->save();
        } else {
            Log::warning("Subscription {$stripeSubscriptionId} not found for status update to {$status}.");
        }
    }

    public function cancelSubscription(int $subscriptionId): void
    {
        $subscription = Subscription::findOrFail($subscriptionId);
        if (!empty($subscription->stripe_subscription_id)) {
            try {
                $this->stripe->subscriptions->cancel($subscription->stripe_subscription_id, []);
                $subscription->status = 'canceled';
                $subscription->save();
            } catch (ApiErrorException $e) {
                $message = "Failed to cancel Stripe subscription {$subscription->stripe_subscription_id} for local subscription ID {$subscriptionId}: {$e->getMessage()}";
                Log::error($message);
                throw new \RuntimeException($message, 0, $e);
            }
        }
    }
}