Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('subscriptions', SubscriptionController::class);
    Route::post('subscriptions/{subscription}/renew', [SubscriptionController::class, 'renew'])
        ->middleware('throttle:10,1')
        ->name('subscriptions.renew');

    Route::resource('teams', TeamController::class);
    Route::post('teams/{team}/invite', [TeamController::class, 'invite'])
        ->middleware('throttle:10,1')
        ->name('teams.invite');
    Route::delete('teams/{team}/invite/{user}', [TeamController::class, 'revokeInvitation'])
        ->name('teams.invite.revoke');
    Route::post('teams/{team}/switch', [TeamController::class, 'switch'])
        ->name('teams.switch');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::put('notifications', [NotificationController::class, 'update'])->name('notifications.update');
    Route::post('notifications/whatsapp/test', [NotificationController::class, 'testWhatsApp'])
        ->middleware('throttle:10,1')
        ->name('notifications.whatsapp.test');
});

Route::fallback([FallbackController::class, 'handle']);