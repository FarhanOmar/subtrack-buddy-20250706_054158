protected function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email:rfc,dns|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ];
    }

    public function handleSignup()
    {
        $key = 'signup|' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw new ThrottleRequestsException('Too many signup attempts. Please try again later.');
        }
        RateLimiter::hit($key, 60);

        $validated = $this->validate();

        try {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            Auth::login($user);
        } catch (\Exception $e) {
            Log::error('Signup error: ' . $e->getMessage());
            $this->addError('signup', 'An unexpected error occurred. Please try again.');
            return;
        }

        session()->flash('success', 'Account created successfully.');

        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.signup');
    }
}