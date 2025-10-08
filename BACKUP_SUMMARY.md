# Admin API Updates and Backup Summary

Date: 2025-09-30 14:27 (+01:00)

## What changed

- **[KYC admin endpoints hardened]** `app/Http/Controllers/KycController.php`
  - `index(Request)`: filters (`status`, `q`), pagination (`per_page`), try/catch and consistent JSON.
  - `show($id)`: try/catch with 404 and error shape.
  - `validateKyc($id)`: sets `approved`, clears `rejection_reason`, notifies user, error handling.
  - `rejectKyc($id, Request)`: supports `reason`, sets `rejection_reason`, notifies user, error handling.
  - `destroy($id)`: error handling and consistent responses.

- **[Users admin API added]** `app/Http/Controllers/Admin/UserAdminController.php`
  - `index(Request)`: list users with filters (`q`, `role`, `is_active`) + pagination.
  - `show($id)`: user details with `kycProfile`.
  - `toggleActive($id)`: activate/deactivate, prevents self-deactivation for current admin.
  - `setRole($id, Request)`: assign role `admin|user`, prevents removing the last active admin.

- **[Routes wired]** `routes/api.php`
  - Admin routes under prefix `/api/admin` and middlewares `auth:sanctum`, `can:admin`.
    - `GET /api/admin/kyc-profiles`
    - `GET /api/admin/kyc-profiles/{id}`
    - `POST /api/admin/kyc-profiles/{id}/validate`
    - `POST /api/admin/kyc-profiles/{id}/reject` (optional JSON `{ "reason": "..." }`)
    - `DELETE /api/admin/kyc-profiles/{id}`
    - `GET /api/admin/users`
    - `GET /api/admin/users/{id}`
    - `POST /api/admin/users/{id}/toggle-active`
    - `POST /api/admin/users/{id}/role` (JSON `{ "role": "admin|user" }`)

- **[Auth Gate confirmed]** `app/Providers/AuthServiceProvider.php` includes `Gate::define('admin', ...)`.

- **[DB schema]** Added KYC rejection reason
  - New migration: `database/migrations/2025_09_30_140000_add_rejection_reason_to_kyc_profiles_table.php`
  - Adds nullable `rejection_reason` (TEXT) after `status` in `kyc_profiles`.

- **[KYC document workflow for frontend]**
  - Model: `app/Models/KycDocument.php`
  - Migrations:
    - `database/migrations/2025_09_30_160000_create_kyc_documents_table.php`
    - `database/migrations/2025_09_30_160100_add_submitted_to_kyc_profiles_table.php`
  - Controller: `app/Http/Controllers/KycController.php`
    - New endpoints for user flow: `requirements`, `uploadDocument`, `replaceDocument`, `deleteDocument`, `uploadSelfie`, `submit`, `decision`
    - `status()` enrichi: inclut `submitted`, `requirements`, `documents` [{ id, type, url }]
  - Routes (user, auth required):
    - `GET /api/kyc/requirements?country=BJ`
    - `POST /api/kyc/documents` (multipart `file`, fields: `type`, `side?`)
    - `PUT /api/kyc/documents/{id}` (multipart `file`)
    - `DELETE /api/kyc/documents/{id}`
    - `POST /api/kyc/selfie` (multipart `image`)
    - `POST /api/kyc/submit`
    - `GET /api/kyc/decision`
  - Règle: les admins ne peuvent pas soumettre/charger de documents KYC (403).

- **[User profile hardening]** `app/Http/Controllers/UserProfileController.php`
  - Added try/catch, DB transaction, safe avatar upload with old file cleanup, consistent JSON errors.

## How to apply

1. Install storage symlink (if not already):
   ```bash
   php artisan storage:link
   ```
2. Run migrations:
   ```bash
   php artisan migrate
   ```
3. Ensure admin user(s) exist with `role = 'admin'` and `is_active = true`.
4. Use Sanctum auth; send Bearer token for admin endpoints.

## Postman quick tests

- Headers: `Accept: application/json`
- Auth: `Bearer <ADMIN_TOKEN>`

### List KYC (admin)
```
GET /api/admin/kyc-profiles?status=pending&q=john&per_page=20
```

### Validate KYC
```
POST /api/admin/kyc-profiles/{id}/validate
```

### Reject KYC with reason
```
POST /api/admin/kyc-profiles/{id}/reject
Content-Type: application/json
{
  "reason": "Document illisible"
}
```

### List users (admin)
```
GET /api/admin/users?q=paris&role=user&is_active=1&per_page=20
```

### Toggle user active
```
POST /api/admin/users/{id}/toggle-active
```

### Set user role
```
POST /api/admin/users/{id}/role
Content-Type: application/json
{
  "role": "admin"
}
```

## Notes

- All admin routes are protected by `auth:sanctum` + `can:admin`.
- Error responses follow a consistent shape with `status`, `message`, and optional `error` when `app.debug = true`.
- KYC rejection reasons require the new migration.

## Next suggested improvements (optional)

- Add admin moderation for cagnottes: list/search, force publish/unpublish/delete with audit log.
- Add server-side logging for failures (e.g., avatar delete) via Laravel logging.
- Create a Postman collection JSON for easy import.
