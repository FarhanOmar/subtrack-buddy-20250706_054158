function bootstrap(): Application
{
    $appPath = __DIR__ . '/bootstrap/app.php';
    if (!file_exists($appPath)) {
        throw new RuntimeException('Bootstrap file not found.');
    }
    $app = require $appPath;
    if (!$app instanceof Application) {
        throw new RuntimeException('Invalid application instance returned by bootstrap.');
    }
    return $app;
}

/**
 * Terminate the application.
 *
 * @param Kernel   $kernel
 * @param Request  $request
 * @param Response $response
 */
function terminate(Kernel $kernel, Request $request, Response $response): void
{
    $kernel->terminate($request, $response);
}

try {
    $app = bootstrap();
    $kernel = $app->make(Kernel::class);
    $request = Request::capture();

    $response = null;
    try {
        $response = $kernel->handle($request);
        $response->send();
    } catch (Throwable $e) {
        // Log the exception
        try {
            $logger = $app->make(LoggerInterface::class);
            $logger->error($e->getMessage(), ['exception' => $e]);
        } catch (Throwable $logEx) {
            // If logging fails, ignore to avoid cascading errors
        }

        if (!$response instanceof Response) {
            $response = new Response('An unexpected error occurred.', 500);
            $response->send();
        }
    } finally {
        if ($kernel instanceof Kernel && $request instanceof Request && $response instanceof Response) {
            terminate($kernel, $request, $response);
        }
    }
} catch (Throwable $e) {
    http_response_code(500);
    // Attempt to log if possible
    if (isset($app) && $app instanceof Application) {
        try {
            $app->make(LoggerInterface::class)->error($e->getMessage(), ['exception' => $e]);
        } catch (Throwable $logEx) {
            // ignore
        }
    }
    echo 'An unexpected error occurred.';
}