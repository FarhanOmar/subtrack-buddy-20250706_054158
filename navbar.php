public function __construct(AuthFactory $auth)
    {
        $this->auth = $auth;
        $this->teams = collect();
    }

    public function render()
    {
        $this->user = $this->auth->guard()->user();
        $this->teams = $this->user
            ? $this->user->teams()->get()
            : collect();
        $this->currentTeam = $this->user
            ? $this->user->currentTeam
            : null;

        return view('components.navbar', [
            'user' => $this->user,
            'teams' => $this->teams,
            'currentTeam' => $this->currentTeam,
        ]);
    }
}