public function __construct()
    {
        $this->middleware('auth')->only('logout');
        $this->middleware('throttle:5,1')->only('login');
    }

    protected function transformUser(User $user): array
    {
        return [
            'id'                => $user->id,
            'name'              => $user->name,
            'email'             => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'created_at'        => $user->created_at->toDateTimeString(),
            'updated_at'        => $user->updated_at->toDateTimeString(),
        ];
    }

    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        if (method_exists($user, 'createTeam')) {
            $user->createTeam([
                'name'          => "{$user->name}'s Team",
                'personal_team' => true,
            ]);
        }

        event(new Registered($user));
        Auth::login($user);

        return response()->json([
            'user' => $this->transformUser($user),
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        if (! $user->hasVerifiedEmail()) {
            Auth::logout();
            return response()->json(['message' => 'Email not verified.'], 403);
        }

        return response()->json([
            'user' => $this->transformUser($user),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([], 204);
    }

    public function verifyEmail(Request $request, $id, $hash)
    {
        if (! URL::hasValidSignature($request)) {
            return response()->json(['message' => 'Invalid or expired verification link.'], 403);
        }

        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid or expired verification link.'], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return response()->json([
            'message' => 'Email successfully verified.',
            'user'    => $this->transformUser($user),
        ], 200);
    }

    public function sendPasswordResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json(['status' => __($status)]);
        }

        return response()->json(['email' => __($status)], 400);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|string|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['status' => __($status)]);
        }

        return response()->json(['email' => __($status)], 400);
    }
}