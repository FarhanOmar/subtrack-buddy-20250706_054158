function handleRequest(Request $request, Kernel $kernel): Response
{
    return $kernel->handle($request);
}

$request = Request::capture();
$response = handleRequest($request, $kernel);
$response->send();

$kernel->terminate($request, $response);