public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status'   => ['sometimes', 'string', Rule::in(['scheduled', 'snoozed', 'paid'])],
            'due_from' => ['sometimes', 'date'],
            'due_to'   => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $teamIds = $user->teams()->pluck('id');

        $query = Reminder::query()
            ->where(function ($q) use ($user, $teamIds) {
                $q->where('user_id', $user->id);
                if ($teamIds->isNotEmpty()) {
                    $q->orWhereIn('team_id', $teamIds);
                }
            });

        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (!empty($data['due_from'])) {
            $query->where('due_at', '>=', Carbon::parse($data['due_from']));
        }

        if (!empty($data['due_to'])) {
            $query->where('due_at', '<=', Carbon::parse($data['due_to']));
        }

        $perPage = $data['per_page'] ?? 15;
        $reminders = $query->orderBy('due_at', 'asc')->paginate($perPage);

        return response()->json($reminders);
    }

    public function show(int $id): JsonResponse
    {
        $reminder = Reminder::findOrFail($id);
        $this->authorize('view', $reminder);

        return response()->json($reminder);
    }

    public function snooze(Request $request, int $id): JsonResponse
    {
        $reminder = Reminder::findOrFail($id);
        $this->authorize('update', $reminder);

        if (in_array($reminder->status, ['snoozed', 'paid'], true)) {
            return response()->json([
                'message' => 'Cannot snooze a reminder that is already snoozed or paid.'
            ], 422);
        }

        $data = $request->validate([
            'snooze_until' => ['required', 'date', 'after:now'],
        ]);

        $reminder->snooze_until = Carbon::parse($data['snooze_until']);
        $reminder->status = 'snoozed';
        $reminder->save();

        return response()->json([
            'message'  => 'Reminder snoozed successfully.',
            'reminder' => $reminder,
        ]);
    }

    public function reschedule(Request $request, int $id): JsonResponse
    {
        $reminder = Reminder::findOrFail($id);
        $this->authorize('update', $reminder);

        if ($reminder->status === 'paid') {
            return response()->json([
                'message' => 'Cannot reschedule a reminder that is already paid.'
            ], 422);
        }

        $data = $request->validate([
            'due_at' => ['required', 'date', 'after:now'],
        ]);

        $reminder->due_at = Carbon::parse($data['due_at']);
        $reminder->status = 'scheduled';
        $reminder->save();

        return response()->json([
            'message'  => 'Reminder rescheduled successfully.',
            'reminder' => $reminder,
        ]);
    }

    public function markPaid(Request $request, int $id): JsonResponse
    {
        $reminder = Reminder::findOrFail($id);
        $this->authorize('update', $reminder);

        if ($reminder->status === 'paid') {
            return response()->json([
                'message' => 'Reminder is already marked as paid.'
            ], 422);
        }

        $reminder->status  = 'paid';
        $reminder->paid_at = Carbon::now();
        $reminder->save();

        return response()->json([
            'message'  => 'Reminder marked as paid.',
            'reminder' => $reminder,
        ]);
    }
}