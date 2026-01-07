<h1>Login</h1>

<div style="background: #f0f0f0; padding: 10px; border: 1px solid #ccc; margin-bottom: 20px;">
    <h3>Debug Session Data</h3>
    <pre><?php
    echo "Session ID: " . session_id() . "\n";
    echo "Cookie Header: " . ($_SERVER['HTTP_COOKIE'] ?? 'Not Set') . "\n";
    echo "Session Save Path: " . session_save_path() . "\n";
    echo "Session Data: ";
    var_dump($_SESSION);
    echo "Cookies: ";
    var_dump($_COOKIE);

    echo "CSRF: " . $_SESSION['_csrf'] . "\n";
    ?></pre>
</div>

<form method="post" action="/login">
    <input type="hidden" name="_token" value="<?= csrf_token() ?>">
    <div style="margin-bottom: 10px;">
        <label>Email: <input type="email" name="email" required></label>
    </div>
    <div style="margin-bottom: 10px;">
        <label>Password: <input type="password" name="password" required></label>
    </div>
    <button type="submit">Login</button>
</form>
<p>Don't have an account? <a href="/register">Register here</a></p>
