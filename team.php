public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get all members of the team.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_user')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    /**
     * Get all pending invites for the team.
     */
    public function invites(): HasMany
    {
        return $this->hasMany(TeamInvite::class)
                    ->whereNull('accepted_at');
    }
}