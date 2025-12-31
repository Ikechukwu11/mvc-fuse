<h1>Register</h1>
<form method="post" action="/register">
    <input type="hidden" name="_token" value="<?= csrf_token() ?>">
    <div style="margin-bottom: 10px;">
        <label>Name: <input type="text" name="name" required></label>
    </div>
    <div style="margin-bottom: 10px;">
        <label>Email: <input type="email" name="email" required></label>
    </div>
    <div style="margin-bottom: 10px;">
        <label>Password: <input type="password" name="password" required></label>
    </div>
    <button type="submit">Register</button>
</form>
<p>Already have an account? <a href="/login">Login here</a></p>
