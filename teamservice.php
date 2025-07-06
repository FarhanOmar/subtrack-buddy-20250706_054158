* @var array
     */
    protected array $validRoles = [
        'owner',
        'admin',
        'member',
    ];

    /**
     * Create a new team and assign the owner.
     *
     * @param  string  $name
     * @param  int     $ownerId
     * @return Team
     */
    public function createTeam(string $name, int $ownerId): Team
    {
        return DB::transaction(function () use ($name, $ownerId) {
            $owner = User::findOrFail($ownerId);

            $team = Team::create([
                'name'     => $name,
                'owner_id' => $owner->id,
            ]);

            $team->members()->attach($owner->id, [
                'role' => 'owner',
            ]);

            return $team;
        });
    }

    /**
     * Add a member to a team.
     *
     * @param  int  $teamId
     * @param  int  $userId
     * @return void
     */
    public function addMember(int $teamId, int $userId): void
    {
        DB::transaction(function () use ($teamId, $userId) {
            $team = Team::findOrFail($teamId);
            $user = User::findOrFail($userId);

            if ($team->members()->wherePivot('user_id', $user->id)->exists()) {
                throw new InvalidArgumentException('User is already a member of this team.');
            }

            $team->members()->attach($user->id, [
                'role' => 'member',
            ]);
        });
    }

    /**
     * Remove a member from a team.
     *
     * @param  int  $teamId
     * @param  int  $userId
     * @return void
     */
    public function removeMember(int $teamId, int $userId): void
    {
        $team = Team::findOrFail($teamId);
        $user = User::findOrFail($userId);

        $relation = $team->members()->wherePivot('user_id', $user->id)->first();

        if (! $relation) {
            throw new InvalidArgumentException('User is not a member of this team.');
        }

        if ($relation->pivot->role === 'owner') {
            throw new InvalidArgumentException('Cannot remove the owner of the team.');
        }

        $team->members()->detach($user->id);
    }

    /**
     * Assign or change a member's role in a team.
     *
     * @param  int     $teamId
     * @param  int     $userId
     * @param  string  $role
     * @return void
     */
    public function assignRole(int $teamId, int $userId, string $role): void
    {
        if (! in_array($role, $this->validRoles, true)) {
            throw new InvalidArgumentException("Invalid role: {$role}");
        }

        // Disallow changing or assigning the 'owner' role via this method
        if ($role === 'owner') {
            throw new InvalidArgumentException("Cannot assign the 'owner' role via this method.");
        }

        $team = Team::findOrFail($teamId);
        $user = User::findOrFail($userId);

        $relation = $team->members()->wherePivot('user_id', $user->id)->first();

        if (! $relation) {
            throw new InvalidArgumentException('User is not a member of this team.');
        }

        if ($relation->pivot->role === 'owner') {
            throw new InvalidArgumentException('Cannot change role of the team owner.');
        }

        $team->members()->updateExistingPivot($user->id, [
            'role' => $role,
        ]);
    }

    /**
     * Get all subscriptions for a team.
     *
     * @param  int  $teamId
     * @return Collection|Subscription[]
     */
    public function getTeamSubscriptions(int $teamId): Collection
    {
        $team = Team::findOrFail($teamId);

        return Subscription::where('team_id', $team->id)
            ->orderBy('next_billing_date', 'asc')
            ->get();
    }
}