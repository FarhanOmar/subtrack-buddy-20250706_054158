function render(int $year, string $appName, array $scripts, bool $debug, string $debugScript): void
{
    ?>
    </div><!-- /.content-wrapper -->
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">&copy; <?= htmlspecialchars((string)$year, ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>. All rights reserved.</span>
        </div>
    </footer>
    <?php foreach ($scripts as $script): ?>
        <script src="<?= htmlspecialchars($script, ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php endforeach; ?>
    <?php if ($debug): ?>
        <script src="<?= htmlspecialchars($debugScript, ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php endif; ?>
    </body>
    </html>
    <?php
}

// Prepare data and invoke the render function.
$year        = (int) date('Y');
$appName     = config('app.name', 'SubTrack Buddy');
$scripts     = [
    mix('js/app.js'),
    mix('js/vendor.js'),
];
$debug       = (bool) config('app.debug');
$debugScript = asset('js/debug.js');

render($year, $appName, $scripts, $debug, $debugScript);
?>