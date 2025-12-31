<h1>Login</h1>
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
