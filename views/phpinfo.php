<style>
  ul {
    padding-left: 20px;
    list-style-type: none; /* Remove default bullets */
  }

  ul li {
    padding-left: 10px;
  }

  pre {
    white-space: pre-wrap; /* Makes the content wrap if it overflows */
    word-wrap: break-word; /* Breaks long words onto the next line */
    max-width: 100%; /* Ensures it doesn't stretch too far */
  }

  strong {
    font-weight: bold;
  }
</style>

<div class="center">
  <h2><?= e('SERVER Variables') ?></h2>

  <?php
  // Output SERVER variables
  echo "<ul>";
  foreach ($_SERVER as $key => $value) {
      echo "<li><strong>$key</strong>: $value</li>";
  }
  echo "</ul>";

  // Output ENV variables
  echo "<h2>ENV Variables</h2>";
  echo "<ul>";
  foreach ($_ENV as $key => $value) {
      echo "<li><strong>$key</strong>: $value</li>";
  }
  echo "</ul>";

  // Output Session Debug Data with foreach
  echo "<h3>Debug Session Data</h3>";
  echo "<ul>";

  // Session ID
  echo "<li><strong>Session ID</strong>: " . session_id() . "</li>";

  // Cookie Header
  echo "<li><strong>Cookie Header</strong>: " . ($_SERVER['HTTP_COOKIE'] ?? 'Not Set') . "</li>";

  // Session Save Path
  echo "<li><strong>Session Save Path</strong>: " . session_save_path() . "</li>";

  // Session Data
  echo "<li><strong>Session Data</strong>:</li>";
  foreach ($_SESSION as $key => $value) {
      echo "<li><strong>$key</strong>: " . htmlspecialchars(print_r($value, true)) . "</li>";
  }

  // Cookies
  echo "<li><strong>Cookies</strong>:</li>";
  foreach ($_COOKIE as $cookieName => $cookieValue) {
      echo "<li><strong>$cookieName</strong>: " . htmlspecialchars($cookieValue) . "</li>";
  }

  // CSRF Token
  echo "<li><strong>CSRF</strong>: " . ($_SESSION['_csrf'] ?? 'Not Set') . "</li>";
  echo "</ul>";
  ?>
</div>
