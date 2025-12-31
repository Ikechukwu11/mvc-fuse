# PHP MVC Framework (No Composer)

Lightweight PHP MVC with server-driven SPA option (Fuse). No external dependencies. Simple router, controllers, views, and a CLI runner for common tasks.

## Quick Start

- Start dev server: `php -S localhost:8000 -t public`
- Open: http://localhost:8000
- List routes: `php runner route:list`
- Generate controller: `php runner make:controller Blog`

## Project Structure

- `public/` front controller and web server rewrite
- `system/engine/` core framework classes (Core, Http, Fuse, Support)
- `routes/` route definitions
- `app/Controllers/` controllers (example: HomeController)
- `views/` templates and layouts
- `config/` app and database configs
- `runner` CLI commands
- `storage/` logs and cache

## Flow

1. `public/index.php` boots the app via `Engine\Bootstrap::init()`
2. `Engine\Core\Kernel` loads routes and dispatches actions
3. Controllers return HTML via global `view()` or JSON arrays
4. `Engine\Support\View` renders PHP templates from `views/`
5. Optional layout: `view('home', $data, 'layouts/main')`

## Views and Layouts

- Views reside in `views/`
- Global function `view($name, array $data = [], ?string $layout = null): string`
- Layouts are templates that receive `$content` plus any `$data`
- Escape helper: `e($value)` for HTML-escaping

## Routing

- Define routes in `routes/web.php`
- Supported methods: GET, POST, PUT, PATCH, DELETE
- Path params: `/users/{id}` passed to handlers as arguments

```php
use App\Controllers\HomeController;
$router->get('/', [HomeController::class, 'index']);
```

## Fuse (SPA Components)

Server-driven components for interactive UIs without complex JavaScript build steps.

### Basic Usage

1. **Create Component**: Create a class in `app/Fuse/` extending `Engine\Fuse\Component`.
2. **Render**: Use `<?= fuse('ComponentName') ?>` in your views.
3. **Scripts**: Include `<?= fuse_scripts() ?>` in your layout footer.
4. **Interactions**: Use `fuse:click="method"` and `fuse:model="property"`.

### Advanced Features

#### Server-Side Navigation (Redirects)

Trigger a client-side redirect from your component logic:

```php
public function login() {
    if ($success) {
        $this->redirect('/dashboard');
    }
}
```

#### Dynamic Layouts

Change the layout for a component dynamically (useful for full-page components):

```php
public function mount() {
    $this->setLayout('layouts/auth');
}
```

#### Loading Indicators

- **Global**: A top progress bar shows by default during requests.
- **Form-Specific**: Use `fuse:loading-target` to show a specific element (and hide global loader) during an action.

```html
<button fuse:click="save" fuse:loading-target="#spinner">Save</button>
<div id="spinner" style="display:none">Saving...</div>
```

#### SPA Navigation

Use `fuse:navigate` on links to enable SPA-style transitions (no full page reload):

```html
<a href="/profile" fuse:navigate>Profile</a>
```

## Runner (CLI)

- `php runner help` shows available commands
- `route:list` outputs defined routes
- `make:controller Name` scaffolds a controller
- `make:migration Name` scaffolds a migration

## Database & Migrations

- **Supported Drivers**: SQLite, MySQL, PostgreSQL (via `.env`)
- **Migrations**: `php runner migrate`
- **Query Builder**:

```php
$users = qb('users')->where('active', 1)->get();
```

## Middleware

- `AuthMiddleware`: Protects routes.
- `CsrfMiddleware`: Enforces CSRF on state-changing requests.

### Custom Middleware

Generate with `php runner make:middleware MyMiddleware`.

```php
$router->get('/admin', [AdminController::class, 'index'], [
    new AuthMiddleware(),
    new IsAdmin()
]);
```

## Conventions

- Return HTML: string or `[status, html]`
- Return JSON: arrays are sent as JSON automatically
- Always escape user-facing values with `e()`
- Keep controllers thin; move presentation to views

## Maintenance

- Update this README whenever routes, structure, or helpers change
- Add new runner commands as the framework evolves
