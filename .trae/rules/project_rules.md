# Trae Project Rules

## Required Commands

- Start dev server: `php -S localhost:8000 -t public`
- List routes: `php runner route:list`
- Generate controller: `php runner make:controller Name`
- **Mobile Build**: `php runner mobile:run` (Requires Android SDK)
- **Mobile Init**: `php runner mobile:init` (Scaffolds Android project)

## Editing Rules

- Always update README.md when architecture, routes, helpers, or structure change
- **Add Comments**: All new classes and methods must have PHPDoc comments explaining their purpose
- Prefer editing existing files over creating new ones unless necessary
- Do not commit secrets or credentials
- Keep controllers minimal; use global `view()` with layouts

## Mobile Development Rules

- **Entry Point**: Mobile-specific boot logic lives in `app/Mobile/boot.php`.
- **Engine Core**: Mobile infrastructure logic is in `system/engine/Mobile/`.
- **Native Bridge**: Android Kotlin code is in `native/android/`.
- **Bundling**: All PHP code is bundled into `native/android/app/src/main/assets/laravel_bundle.zip` during build.
- **No Laravel**: Do not introduce Laravel dependencies; use the custom `Engine` namespace.
- **Verification**: Verify mobile changes by running `php runner mobile:run` and checking the emulator/device.

## File Conventions

- Views live in `views/`; use layouts under `views/layouts/`
- Global helpers in `system/engine/helpers.php` (`view()`, `e()`)
- Routes in `routes/web.php`; use `[Controller::class, 'method']`

## Verification

- After changes, run dev server and manually check `http://localhost:8000/`
- Use `route:list` to verify routing updates
- For mobile, ensure `native/android` compiles via `php runner mobile:run`
