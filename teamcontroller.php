public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $teams = $user->teams()->withCount('users')->get();
        return response()->json(['data' => $teams], 200);
    }

    public function show(int $id): JsonResponse
    {
        $team = Team::with('users')->findOrFail($id);
        $this->authorize('view', $team);
        return response()->json(['data' => $team], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $team = new Team($data);
        $team->owner_id = Auth::id();
        $team->save();

        $team->users()->attach(Auth::id(), ['role' => 'owner']);

        return response()->json(['data' => $team], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $team = Team::findOrFail($id);
        $this->authorize('update', $team);

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $team->update($data);

        return response()->json(['data' => $team], 200);
    }

    public function destroy(int $id): JsonResponse
    {
        $team = Team::findOrFail($id);
        $this->authorize('delete', $team);

        // Detach all users to clean up pivot records
        $team->users()->detach();
        $team->delete();

        return response()->json(null, 204);
    }

    public function inviteMember(Request $request, int $teamId): JsonResponse
    {
        $team = Team::findOrFail($teamId);
        $this->authorize('update', $team);

        $data = $request->validate([
            'email' => ['required', 'email', Rule::exists('users', 'email')],
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($team->users()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'User is already a member'], 422);
        }

        $team->users()->attach($user->id, ['role' => 'member']);
        $user->notify(new TeamInvitationNotification($team, Auth::user()));

        return response()->json(['message' => 'Invitation sent'], 200);
    }

    public function removeMember(Request $request, int $teamId, int $userId): JsonResponse
    {
        $team = Team::findOrFail($teamId);
        $this->authorize('update', $team);

        if ((int) $userId === $team->owner_id) {
            return response()->json(['message' => 'Cannot remove the team owner'], 403);
        }

        if (! $team->users()->where('user_id', $userId)->exists()) {
            return response()->json(['message' => 'User is not a member'], 404);
        }

        $team->users()->detach($userId);

        return response()->json(['message' => 'Member removed'], 200);
    }
}