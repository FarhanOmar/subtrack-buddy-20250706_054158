function getDatabaseConnection(): PDO
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $db   = getenv('DB_NAME') ?: 'subtrack_buddy';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    return new PDO($dsn, $user, $pass, $options);
}

function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function getClientIp(): string
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function getRateLimitFile(): string
{
    $ip = getClientIp();
    $hash = hash('sha256', $ip);
    return sys_get_temp_dir() . '/login_attempts_' . $hash . '.json';
}

function loadRateLimit(): array
{
    $file = getRateLimitFile();
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data) && isset($data['count'], $data['first_time'])) {
            return $data;
        }
    }
    return ['count' => 0, 'first_time' => time()];
}

function saveRateLimit(array $data): void
{
    $file = getRateLimitFile();
    file_put_contents($file, json_encode($data), LOCK_EX);
}

function clearRateLimit(): void
{
    $file = getRateLimitFile();
    if (file_exists($file)) {
        @unlink($file);
    }
}

function rateLimitCheck(): void
{
    $maxAttempts = 5;
    $decaySeconds = 900; // 15 minutes
    $now = time();
    $data = loadRateLimit();
    if ($now - $data['first_time'] > $decaySeconds) {
        $data['count'] = 0;
        $data['first_time'] = $now;
        saveRateLimit($data);
    }
    if ($data['count'] >= $maxAttempts) {
        http_response_code(429);
        render(['Too many login attempts. Please try again later.'], []);
        exit;
    }
}

function incrementRateLimit(): void
{
    $now = time();
    $data = loadRateLimit();
    $data['count']++;
    if (!isset($data['first_time'])) {
        $data['first_time'] = $now;
    }
    saveRateLimit($data);
}

function render(array $errors = [], array $old = []): void
{
    $email = htmlspecialchars($old['email'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $remember = isset($old['remember']) ? 'checked' : '';
    $csrfToken = generateCsrfToken();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Login - SubTrack Buddy</title>
        <link rel="stylesheet" href="/css/app.css">
    </head>
    <body>
        <div class="login-container">
            <h1>Login</h1>
            <?php if ($errors): ?>
                <div class="errors">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="post" action="login.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= $email ?>" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="remember" <?= $remember ?>> Remember Me
                    </label>
                </div>
                <button type="submit">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
}

function handleRememberMeLogin(): void
{
    if (!empty($_COOKIE['remember_me'])) {
        $token = $_COOKIE['remember_me'];
        if (preg_match('/^[0-9a-f]{64}$/', $token)) {
            try {
                $pdo = getDatabaseConnection();
                $stmt = $pdo->prepare('SELECT user_id FROM remember_tokens WHERE token_hash = ? AND expires_at >= NOW() LIMIT 1');
                $stmt->execute([hash('sha256', $token)]);
                $row = $stmt->fetch();
                if ($row) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$row['user_id'];
                    header('Location: dashboard.php');
                    exit;
                }
            } catch (Exception $e) {
                // fail silently
            }
        }
    }
}

function handleLogin(): void
{
    rateLimitCheck();

    $errors = [];
    $old = [];

    $csrfToken = $_POST['csrf_token'] ?? '';
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    $old['email'] = $email;
    if ($remember) {
        $old['remember'] = true;
    }

    if (!verifyCsrfToken($csrfToken)) {
        $errors[] = 'Invalid CSRF token.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($password === '' || strlen($password) < 8) {
        $errors[] = 'Please enter your password (minimum 8 characters).';
    }
    if ($errors) {
        incrementRateLimit();
        render($errors, $old);
        exit;
    }

    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
    } catch (Exception $e) {
        incrementRateLimit();
        render(['An error occurred. Please try again later.'], $old);
        exit;
    }

    if (!$user || !password_verify($password, $user['password_hash'])) {
        incrementRateLimit();
        render(['Email or password is incorrect.'], $old);
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];

    if ($remember) {
        $rawToken = bin2hex(random_bytes(32));
        $expires = time() + 604800; // 7 days
        setcookie('remember_me', $rawToken, [
            'expires' => $expires,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        try {
            $stmt = $pdo->prepare('INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))');
            $stmt->execute([(int)$user['id'], hash('sha256', $rawToken), $expires]);
        } catch (Exception $e) {
            // fail silently
        }
    }

    clearRateLimit();
    header('Location: dashboard.php');
    exit;
}

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

handleRememberMeLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleLogin();
} else {
    render();
}