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

144→- Update this README whenever routes, structure, or helpers change
145→- Add new runner commands as the framework evolves

## Native Mobile Architecture (Study: NativePHP v3)

- Purpose: Understand how NativePHP v3 integrates PHP into mobile apps and adapt ideas to our engine.
- Scope: Android/iOS build pipeline, runtime bridge, dev server, UI edge components, packaging, and config.

**Overview**

- Mobile shell app embeds a PHP runtime and loads your application from a bundled ZIP.
- A native bridge exposes device features to PHP via function calls (e.g., Camera, Geolocation).
- Blade-like “native” tags become edge components; rendering sends structured UI data to the native layer.
- A dev server and WebSocket enable hot reload and bundle updates during development.

**Core Package**

- Dependencies include Workerman, ReactPHP, and Laravel components for CLI and service provider wiring. See [composer.json](file:///d:/htdocs/nativephp/v3/mobile/composer.json#L24-L38).
- Service provider registers commands, routes, middleware, views, and Blade directives. See [NativeServiceProvider.php](file:///d:/htdocs/nativephp/v3/mobile/src/NativeServiceProvider.php#L45-L66).
- Middleware pushes edge UI data to native after each response. See [RenderEdgeComponents.php](file:///d:/htdocs/nativephp/v3/mobile/src/Http/Middleware/RenderEdgeComponents.php#L12-L20).
- Platform-specific hot files for Vite dev servers. See [NativeServiceProvider.php](file:///d:/htdocs/nativephp/v3/mobile/src/NativeServiceProvider.php#L224-L244).

**Edge UI Components**

- Blade precompiler transforms native: tags into x-native-\* tags. See [NativeTagPrecompiler.php](file:///d:/htdocs/nativephp/v3/mobile/src/Edge/NativeTagPrecompiler.php#L19-L36).
- Components collect into a tree during view rendering; children are handled via context stacking. See [EdgeComponent.php:render](file:///d:/htdocs/nativephp/v3/mobile/src/Edge/Components/EdgeComponent.php#L61-L81) and [native-placeholder-with-children.blade.php](file:///d:/htdocs/nativephp/v3/mobile/src/resources/views/components/native-placeholder-with-children.blade.php#L1-L7).
- After response, the collected components are pushed to native via nativephp_call. See [Edge.php:set](file:///d:/htdocs/nativephp/v3/mobile/src/Edge/Edge.php#L123-L136).

**Android Runtime Bridge**

- Android app loads bundled PHP and executes bootstrap scripts per request. See [native.php](file:///d:/htdocs/nativephp/v3/mobile/bootstrap/android/native.php#L70-L115).
- JNI layer exposes functions to PHP:
  - NativePHPCan checks registry for function existence. See [php_bridge.c](file:///d:/htdocs/nativephp/v3/mobile/resources/androidstudio/app/src/main/cpp/php_bridge.c#L513-L567) and [BridgeRouter.kt](file:///d:/htdocs/nativephp/v3/mobile/resources/androidstudio/app/src/main/java/com/nativephp/mobile/bridge/BridgeRouter.kt#L149-L153).
  - NativePHPCall routes JSON params to Kotlin function and returns JSON result. See [bridge_jni.cpp](file:///d:/htdocs/nativephp/v3/mobile/resources/androidstudio/app/src/main/cpp/bridge_jni.cpp#L124-L196) and [BridgeRouter.kt](file:///d:/htdocs/nativephp/v3/mobile/resources/androidstudio/app/src/main/java/com/nativephp/mobile/bridge/BridgeRouter.kt#L164-L230).
- Single-request handler initializes and shuts down embedded PHP, sets env, parses GET/POST, and returns HTTP response text. See [php_bridge.c](file:///d:/htdocs/nativephp/v3/mobile/resources/androidstudio/app/src/main/cpp/php_bridge.c#L150-L240).

**iOS Runtime (High-Level)**

- Swift bridge mirrors Android pattern with function registry and native UI adapters.
- Xcode project contains bridge functions, UI scaffolding, and hot reload client code (see xcode/NativePHP/\*).

**Dev Server & Hot Reload**

- WebSocket server tracks clients, responds to ping, instructs bundle downloads, and broadcasts file changes. See [WebSocketServer.php](file:///d:/htdocs/nativephp/v3/mobile/src/Server/WebSocketServer.php#L20-L86).
- File watcher and HTTP server serve bundles and notify devices to refresh when files change (see src/Server/\*).
- Vite hot files are platform-specific for simultaneous iOS/Android development. See [NativeServiceProvider.php](file:///d:/htdocs/nativephp/v3/mobile/src/NativeServiceProvider.php#L232-L244).

**Packaging & Build Pipeline**

- Android:
  - Prepares build: cleans caches, updates manifest, icons, splash, orientation, deep links, Firebase, and Gradle config. See [PreparesBuild.php](file:///d:/htdocs/nativephp/v3/mobile/src/Traits/PreparesBuild.php#L55-L70,L100-L167,L306-L423).
  - Bundles Laravel app code into assets/laravel_bundle.zip with composer --no-dev and autoloader optimization. See [PreparesBuild.php](file:///d:/htdocs/nativephp/v3/mobile/src/Traits/PreparesBuild.php#L200-L303).
  - Compiles plugins and runs Gradle tasks; optionally signs bundles/APKs. See [RunsAndroid.php](file:///d:/htdocs/nativephp/v3/mobile/src/Traits/RunsAndroid.php#L393-L544) and [PackageCommand.php](file:///d:/htdocs/nativephp/v3/mobile/src/Commands/PackageCommand.php#L62-L117).
  - Play Store upload supported via Google service key and AAB builds. See [PackageCommand.php](file:///d:/htdocs/nativephp/v3/mobile/src/Commands/PackageCommand.php#L565-L601).
- iOS:
  - Build and archive with signing configuration and optional App Store upload. See [PackageCommand.php](file:///d:/htdocs/nativephp/v3/mobile/src/Commands/PackageCommand.php#L241-L355).

**Configuration Essentials**

- App identity, versions, deep links, dev server ports/paths, and Android build options live in config. See [nativephp.php](file:///d:/htdocs/nativephp/v3/mobile/config/nativephp.php#L16-L43,L55-L69,L195-L227,L163-L183).
- Internal flags indicate when running inside a NativePHP shell app and which platform. See [nativephp-internal.php](file:///d:/htdocs/nativephp/v3/mobile/config/nativephp-internal.php#L9-L19).
- Bundle cleanup excludes secrets and dev-only files; env keys are sanitized before bundling. See [nativephp.php](file:///d:/htdocs/nativephp/v3/mobile/config/nativephp.php#L107-L115,L127-L133).

**Notes for This MVC Project**

- Our engine’s mobile entrypoint mirrors the bootstrap concept, but targets MVC instead of Laravel. See [mobile_boot.php](file:///d:/htdocs/php/mvc/system/engine/Mobile/mobile_boot.php#L20-L38).
- Bundling and build flow are implemented in our Manager with similar asset ZIP packaging. See [Manager.php](file:///d:/htdocs/php/mvc/system/engine/Mobile/Manager.php#L117-L132).
- Commands for verification and local dev:
  - Start web dev server: php -S localhost:8000 -t public
  - Mobile build/init: php runner mobile:run, php runner mobile:init
- Conventions for mobile are recorded in project rules. See [project_rules.md](file:///d:/htdocs/php/mvc/.trae/rules/project_rules.md).
