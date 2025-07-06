* @var array<int,string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for arrays and JSON.
     *
     * @var array<int,string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Get all software subscriptions for the user.
     *
     * @return HasMany
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get all teams the user belongs to.
     *
     * @return BelongsToMany
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
                    ->withTimestamps();
    }

    /**
     * Determine if the user has an active Pro subscription.
     *
     * @return bool
     */
    public function isPro(): bool
    {
        return $this->subscriptions()
                    ->where('status', 'active')
                    ->where('expires_at', '>', Carbon::now())
                    ->exists();
    }

    /**
     * Check if the user belongs to a given team.
     *
     * @param  int  $teamId
     * @return bool
     */
    public function hasTeam(int $teamId): bool
    {
        return $this->teams()
                    ->where('teams.id', $teamId)
                    ->exists();
    }
}