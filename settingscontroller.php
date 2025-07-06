public function __construct()
    {
        $this->middleware('auth');
    }

    public function showSettings(Request $request)
    {
        $user = $request->user();
        $teams = $user->teams()->get();
        $currentTeam = $user->currentTeam;
        $notificationPreferences = $user->notification_preferences ?? [
            'email_notifications' => true,
            'whatsapp_notifications' => false,
        ];

        $billingMethods = [];
        $defaultPaymentMethod = null;

        if (class_exists(\Laravel\Cashier\Cashier::class)) {
            $methodsCacheKey = "stripe_methods_user_{$user->id}";
            $defaultCacheKey = "stripe_default_method_user_{$user->id}";

            if (!Cache::has($methodsCacheKey) || !Cache::has($defaultCacheKey)) {
                $user->createOrGetStripeCustomer();
                $billingMethods = $user->paymentMethods();
                $defaultPaymentMethod = $user->defaultPaymentMethod();

                Cache::put($methodsCacheKey, $billingMethods, now()->addMinutes(10));
                Cache::put($defaultCacheKey, $defaultPaymentMethod, now()->addMinutes(10));
            } else {
                $billingMethods = Cache::get($methodsCacheKey, []);
                $defaultPaymentMethod = Cache::get($defaultCacheKey);
            }
        }

        return view('settings.index', compact(
            'user',
            'teams',
            'currentTeam',
            'notificationPreferences',
            'billingMethods',
            'defaultPaymentMethod'
        ));
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        $user->name = $data['name'];
        if ($user->email !== $data['email']) {
            $user->email = $data['email'];
            $user->email_verified_at = null;
            $user->sendEmailVerificationNotification();
        }
        $user->save();

        return redirect()->route('settings.index')->with('status', 'profile-updated');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password does not match our records.'],
            ]);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        return redirect()->route('settings.index')->with('status', 'password-updated');
    }

    public function updateNotifications(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'email_notifications' => ['required', 'boolean'],
            'whatsapp_notifications' => ['required', 'boolean'],
        ]);

        $user->notification_preferences = [
            'email_notifications' => $data['email_notifications'],
            'whatsapp_notifications' => $data['whatsapp_notifications'],
        ];
        $user->save();

        return redirect()->route('settings.index')->with('status', 'notifications-updated');
    }

    public function updateBilling(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'payment_method_id' => ['required', 'string'],
        ]);

        if (!class_exists(\Laravel\Cashier\Cashier::class)) {
            return redirect()->route('settings.index')->with('error', 'Billing service is not configured.');
        }

        try {
            $user->createOrGetStripeCustomer();
            $user->updateDefaultPaymentMethod($data['payment_method_id']);

            $methodsCacheKey = "stripe_methods_user_{$user->id}";
            $defaultCacheKey = "stripe_default_method_user_{$user->id}";
            Cache::forget($methodsCacheKey);
            Cache::forget($defaultCacheKey);

        } catch (IncompletePayment $exception) {
            Log::warning('Incomplete payment when updating billing for user.', [
                'user_id' => $user->id,
                'payment_method_id' => $data['payment_method_id'],
                'exception' => $exception,
            ]);

            $intentSecret = $exception->payment->client_secret ?? null;

            return redirect()->route('settings.index')->with([
                'status' => 'billing-confirmation-required',
                'intent_secret' => $intentSecret,
            ]);
        } catch (Exception $e) {
            Log::error('Error updating billing method for user.', [
                'user_id' => $user->id,
                'payment_method_id' => $data['payment_method_id'],
                'exception' => $e->getMessage(),
            ]);

            return redirect()->route('settings.index')->with('error', 'An error occurred while updating billing.');
        }

        return redirect()->route('settings.index')->with('status', 'billing-updated');
    }
}