# RUN-ITC Laravel Migration Structure

Project ini dimigrasikan dengan pendekatan hybrid. Struktur lama tetap dipertahankan sebagai referensi sampai modul terkait selesai dipindahkan ke Laravel.

## Target Structure

```txt
app/
  Http/Controllers/
    Auth/
    Admin/
    Profile/
    Notifications/
    CbtOps/
    FilingSystem/
  Http/Middleware/
  Models/
  Services/
  Support/
config/
database/
public/assets/
public/docs/
resources/views/
routes/
storage/
```

## Legacy Mapping

| Legacy path | Laravel target |
| --- | --- |
| `controllers/*.php` | `app/Http/Controllers/...` |
| `models/*.php` | `app/Models/...` |
| `classes/Auth.php` | `app/Services/AuthService.php` |
| `classes/Database.php` | `config/database.php` connections |
| `classes/Session.php` | Laravel session |
| `classes/Csrf.php` | Laravel CSRF middleware |
| `classes/AccessGuard.php` | `app/Http/Middleware/AccessGuard.php` |
| `classes/FtpStorage.php` | `app/Services/FtpStorageService.php` |
| `classes/Helper.php` | `app/Support/Helper.php` |
| `classes/StatusHelper.php` | `app/Support/StatusHelper.php` |
| `classes/ParticipantTableRenderer.php` | `app/Support/ParticipantTableRenderer.php` |
| `includes/head.php` | `resources/views/layouts/partials/head.blade.php` |
| `includes/layout_header.php` | `resources/views/layouts/partials/header.blade.php` |
| `includes/layout_footer.php` | `resources/views/layouts/partials/footer.blade.php` |
| `includes/menu_guard.php` | `app/Http/Middleware/MenuGuard.php` |
| `includes/audit_helper.php` | `app/Services/AuditService.php` |
| `views/*.php` | `resources/views/**/*.blade.php` |
| `modules/auth/*` | `routes/auth.php`, `app/Http/Controllers/Auth`, `resources/views/auth` |
| `modules/admin/*` | `routes/admin.php`, `app/Http/Controllers/Admin`, `resources/views/admin` |
| `modules/profile/*` | `app/Http/Controllers/Profile`, `resources/views/profile` |
| `modules/notifications/*` | `app/Http/Controllers/Notifications`, `resources/views/notifications`, `routes/api.php` |
| `modules/cbt_ops/*` | `routes/cbt_ops.php`, `app/Http/Controllers/CbtOps`, `resources/views/cbt-ops` |
| `modules/cbt_ops/filing_system/*` | `routes/filing_system.php`, `app/Http/Controllers/FilingSystem`, `resources/views/filing-system` |
| `assets/*` | `public/assets/*` |
| `docs/*` | `public/docs/*` |

## Current Milestone

- Laravel 12 skeleton installed.
- Legacy source folders are still present.
- Public assets copied to `public/assets` and `public/docs`.
- Multi database config added for `mysql`, `run`, `bot`, `war`, and `collector`.
- Initial route files added: `auth.php`, `admin.php`, `cbt_ops.php`, `filing_system.php`.
- Initial Blade layouts and placeholder pages added.

## Next Milestone

Migrate real authentication from legacy `controllers/AuthController.php` and `classes/Auth.php` into Laravel `LoginController`/`AuthService`, then replace the placeholder login behavior.
