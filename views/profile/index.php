<h1>Profile</h1>
<p>Welcome, <?= e($user->name) ?></p>

<div style="margin-bottom: 20px; border: 1px solid #ddd; padding: 15px;">
    <h2>Update Info</h2>
    <form method="post" action="/profile">
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <div style="margin-bottom: 10px;">
            <label>Name: <input type="text" name="name" value="<?= e($user->name) ?>" required></label>
        </div>
        <div style="margin-bottom: 10px;">
            <label>Email: <input type="email" name="email" value="<?= e($user->email) ?>" required></label>
        </div>
        <button type="submit">Update Profile</button>
    </form>
</div>

<div style="margin-bottom: 20px; border: 1px solid #ddd; padding: 15px;">
    <h2>Bio</h2>
    <?php if ($bio): ?>
        <div style="background: #f9f9f9; padding: 10px; margin-bottom: 10px; border-left: 3px solid #007bff;">
            <?= nl2br(e($bio)) ?>
        </div>
        <form method="post" action="/profile/bio" style="display:inline;">
            <input type="hidden" name="_method" value="DELETE">
            <input type="hidden" name="_token" value="<?= csrf_token() ?>">
            <button type="submit" onclick="return confirm('Are you sure you want to delete your bio?')">Delete Bio</button>
        </form>
        <hr>
    <?php endif; ?>

    <h3><?= $bio ? 'Edit' : 'Create' ?> Bio</h3>
    <form method="post" action="/profile/bio">
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <div style="margin-bottom: 10px;">
            <textarea name="bio" rows="5" cols="50" placeholder="Tell us about yourself..."><?= e($bio) ?></textarea>
        </div>
        <button type="submit">Save Bio</button>
    </form>
</div>

<p><a href="/dashboard">Back to Dashboard</a> | <a href="/logout">Logout</a></p>
