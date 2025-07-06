private function validateFilters(Request $request)
    {
        $request->validate([
            'date_from' => 'sometimes|date',
            'date_to'   => 'sometimes|date',
            'action'    => 'sometimes|string',
            'search'    => 'sometimes|string',
        ]);
    }

    /**
     * Build filtered query for histories.
     */
    private function getFilteredQuery(Request $request)
    {
        $user = Auth::user();
        $query = History::query();
        $query->where(function($q) use ($user) {
            $q->where('user_id', $user->id);
            if ($user->team_id) {
                $q->orWhere('team_id', $user->team_id);
            }
        });

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('details', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Display history index.
     */
    public function index(Request $request)
    {
        $this->validateFilters($request);

        $histories = $this->getFilteredQuery($request)
            ->with('user')
            ->paginate(20)
            ->appends($request->query());

        return view('history.index', compact('histories'));
    }

    /**
     * Filter histories via AJAX.
     */
    public function filter(Request $request)
    {
        $this->validateFilters($request);

        $histories = $this->getFilteredQuery($request)
            ->with('user')
            ->paginate(20)
            ->appends($request->query());

        return response()->json([
            'html'       => view('history.partials.table', compact('histories'))->render(),
            'pagination' => (string) $histories->links(),
        ]);
    }

    /**
     * Sanitize a value for CSV/XLS to prevent CSV injection.
     */
    private function sanitizeForSpreadsheet($value)
    {
        if ($value === null) {
            return '';
        }
        $value = (string) $value;
        $first = substr($value, 0, 1);
        if (in_array($first, ['=', '+', '-', '@'])) {
            return "'{$value}";
        }
        return $value;
    }

    /**
     * Export filtered histories as CSV.
     */
    public function exportCsv(Request $request)
    {
        $this->validateFilters($request);

        $filename = 'histories_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];
        $columns = ['ID', 'User', 'Action', 'Details', 'Date'];

        $callback = function() use ($columns, $request) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);
            foreach ($this->getFilteredQuery($request)->with('user')->cursor() as $history) {
                fputcsv($handle, [
                    $history->id,
                    $this->sanitizeForSpreadsheet(optional($history->user)->name),
                    $this->sanitizeForSpreadsheet($history->action),
                    $this->sanitizeForSpreadsheet($history->details),
                    $history->created_at->toDateTimeString(),
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export filtered histories as XLS (tab-delimited).
     */
    public function exportXls(Request $request)
    {
        $this->validateFilters($request);

        $filename = 'histories_' . now()->format('Ymd_His') . '.xls';
        $headers = [
            'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];
        $columns = ['ID', 'User', 'Action', 'Details', 'Date'];

        $callback = function() use ($columns, $request) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns, "\t");
            foreach ($this->getFilteredQuery($request)->with('user')->cursor() as $history) {
                fputcsv($handle, [
                    $history->id,
                    $this->sanitizeForSpreadsheet(optional($history->user)->name),
                    $this->sanitizeForSpreadsheet($history->action),
                    $this->sanitizeForSpreadsheet($history->details),
                    $history->created_at->toDateTimeString(),
                ], "\t");
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}