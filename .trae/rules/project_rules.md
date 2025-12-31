# Trae Project Rules

## Required Commands
- Start dev server: `php -S localhost:8000 -t public`
- List routes: `php runner route:list`
- Generate controller: `php runner make:controller Name`

## Editing Rules
- Always update README.md when architecture, routes, helpers, or structure change
- **Add Comments**: All new classes and methods must have PHPDoc comments explaining their purpose
- Prefer editing existing files over creating new ones unless necessary
- Do not commit secrets or credentials
- Keep controllers minimal; use global `view()` with layouts

## File Conventions
- Views live in `views/`; use layouts under `views/layouts/`
- Global helpers in `system/engine/helpers.php` (`view()`, `e()`)
- Routes in `routes/web.php`; use `[Controller::class, 'method']`

## Verification
- After changes, run dev server and manually check `http://localhost:8000/`
- Use `route:list` to verify routing updates

