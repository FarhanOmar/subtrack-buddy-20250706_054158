* @var array
     */
    public array $plans;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->plans = config('pricing.plans', []);
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return View|string
     */
    public function render(): View|string
    {
        return view('components.pricing', [
            'plans' => $this->plans,
        ]);
    }
}