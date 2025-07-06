Route::prefix('v1')->group(function (): void {
    // Public authentication routes with rate limiting
    Route::middleware('throttle:10,1')->group(function (): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('password/email', [AuthController::class, 'sendPasswordResetLink']);
        Route::post('password/reset', [AuthController::class, 'resetPassword']);
    });

    // Protected routes requiring authentication
    Route::middleware('auth:sanctum')->group(function (): void {
        // Auth
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', function (Request $request) {
            return response()->json($request->user());
        });

        // Subscriptions
        Route::apiResource('subscriptions', SubscriptionController::class);
        Route::post('subscriptions/{subscription}/renew', [SubscriptionController::class, 'renew']);
        Route::get('subscriptions/{subscription}/reminders', [ReminderController::class, 'index']);
        Route::post('subscriptions/{subscription}/reminders', [ReminderController::class, 'sendReminder']);

        // Teams
        Route::apiResource('teams', TeamController::class);
        Route::post('teams/{team}/invite', [TeamController::class, 'inviteMember']);
        Route::delete('teams/{team}/members/{member}', [TeamController::class, 'removeMember']);

        // Sidebar widget data
        Route::get('widgets/sidebar', [WidgetController::class, 'sidebarData']);
    });
});