# Laravel Project Instructions

## Technology Stack
- Backend: Laravel 8
- Language: PHP 7.4
- Database: MySQL; Microsoft SQL Server where configured
- Frontend: Blade, JavaScript, jQuery, Bootstrap
- Development Environment: Windows 11, XAMPP
- Administration: Laravel Backpack where installed

Verify actual dependencies and versions in composer.json, package.json, and project configuration before making changes.

## Laravel Development Standards

- Maintain compatibility with PHP 7.4 and Laravel 8.
- Do not introduce PHP 8-only syntax or unsupported framework features.
- Follow existing Laravel conventions and application patterns.
- Prefer Eloquent relationships or the Query Builder where appropriate.
- Avoid unnecessary raw SQL.
- Use Form Request validation when consistent with the existing project.
- Apply policies, gates, middleware, and permission checks to protected functionality.
- Preserve existing authentication and role-based authorization.
- Never assume a route or CRUD interface is secure without examining its access controls.
- Handle file uploads through configured Laravel storage disks.
- Maintain existing storage paths and file-access behavior unless changes are required.
- Ensure database migrations are backward-compatible whenever possible.
- Do not modify previously executed migrations unnecessarily.
- Do not run migrate:fresh, db:wipe, or other destructive database commands without explicit permission.

## Testing

- Use available Laravel/PHP testing tools.
- Run relevant PHPUnit tests when feasible.
- Check PHP syntax after changing PHP files.
- Verify routes, authentication, authorization, database queries, file uploads, and affected business logic.
- Clearly report which checks were actually executed.

## Existing Application Protection

- Treat the application as an established codebase with important business logic.
- Preserve unrelated routes, controllers, models, views, database structures, and user-facing behavior.
- Avoid replacing working implementations solely to adopt newer architectural patterns.
- Explain compatibility and regression risks before major refactoring.
- Prefer minimal patches supported by targeted verification.

## Working Objective

Maintain and improve the existing Laravel application safely, using clean, secure, compatible, and maintainable code without introducing unnecessary dependencies or breaking existing functionality.
