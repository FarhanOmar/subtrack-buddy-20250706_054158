public function __construct()
    {
        $this->middleware('auth');
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;
        $userSettings = $user->settings ?? [];
        $teamSettings = $team ? ($team->settings ?? []) : [];

        return view('settings.index', [
            'userSettings' => $userSettings,
            'teamSettings' => $teamSettings,
        ]);
    }

    public function handleSettingsSave(Request $request)
    {
        $rules = [
            'notification_email' => 'required|email',
            'whatsapp_reminders' => 'required|boolean',
            'reminder_days'      => 'required|integer|min:0|max:365',
            'timezone'           => 'required|timezone',
            'apply_to_team'      => 'sometimes|boolean',
        ];

        $data = $request->validate($rules);

        $newSettings = [
            'notification_email' => $data['notification_email'],
            'whatsapp_reminders' => (bool) $data['whatsapp_reminders'],
            'reminder_days'      => (int) $data['reminder_days'],
            'timezone'           => $data['timezone'],
        ];

        $applyToTeam = $request->boolean('apply_to_team');
        $user = Auth::user();
        $team = $user->currentTeam;

        DB::transaction(function () use ($user, $team, $newSettings, $applyToTeam) {
            $user->settings = array_merge($user->settings ?? [], $newSettings);
            $user->save();

            if ($applyToTeam && $team) {
                $team->settings = array_merge($team->settings ?? [], $newSettings);
                $team->save();
            }
        });

        return redirect()->back()->with('status', __('Settings updated successfully.'));
    }
}