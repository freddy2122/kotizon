<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

# Kotizon API – Frontend (React) Integration Guide

## 1) Environment

Add these variables in your `.env` (already present in `.env.example`):

```
CORS_ALLOW_ORIGINS=http://localhost:3000
FRONTEND_URL=http://localhost:3000

# Social / Providers
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=

TWILIO_SID=
TWILIO_AUTH_TOKEN=
TWILIO_WHATSAPP_FROM=whatsapp:+14155238886

# Filesystem
FILESYSTEM_DISK=public
```

Multiple origins can be comma-separated in `CORS_ALLOW_ORIGINS`.

## 2) Server commands (dev)

```
php artisan migrate
php artisan storage:link
php artisan queue:work   # for queued email / WhatsApp
composer dev             # serves API + Vite + logs + queue listener
```

## 3) Auth flow (API)

- Register: `POST /api/register` { name, email, phone, password }
  - Sends verification code via Email (queued) + WhatsApp (queued)
- Verify: `POST /api/verify` { email, code }
- Login: `POST /api/login` { email, password } → returns Bearer token (Sanctum)
- Current user: `GET /api/me` (Authorization: Bearer <token>)
- Logout: `POST /api/logout`

Rate limiting is applied on `register`, `verify`, `login`, `forgot-password`.

## 4) Password reset (API + redirect to React)

- Request link: `POST /api/forgot-password` { email }
- Reset form (React): your app should route `GET /reset-password?token=...&email=...`
- Backend route `GET /reset-password/{token}` redirects to `${FRONTEND_URL}/reset-password?token=...&email=...`
- Submit new password: `POST /api/reset-password` { token, email, password, password_confirmation }

## 5) KYC and Cagnottes

- KYC is required and must be approved to create/publish a cagnotte.
- Uploads use `Storage::disk('public')` and return `storage/...` URLs.

### KYC API (User)

- **[GET]** `/api/kyc/requirements?country=BJ`
  - Returns dynamic requirements for the given country.
  - 200:
    ```json
    { "status": "success", "requirements": { "required": [{"id":"national_id_front","type":"national_id_front"},{"id":"national_id_back","type":"national_id_back"},{"id":"proof_of_address","type":"proof_of_address"}], "optional": [{"id":"additional_document","type":"other"}] } }
    ```

- **[GET]** `/api/kyc/status`
  - Aggregated KYC status with submitted flag, current documents and requirements.
  - 200:
    ```json
    { "status": "none|pending|approved|rejected", "submitted": false, "requirements": {"required":[],"optional":[]}, "documents": [{"id":"123","type":"proof_of_address","url":"storage/..."}], "profile": {"...":"..."} }
    ```

- **[POST]** `/api/kyc/documents` (multipart/form-data)
  - Body: `type` (ex: proof_of_address), `side?` (front|back), `file` (jpeg|png|pdf, max 5MB)
  - 201:
    ```json
    { "id": "123", "type": "proof_of_address", "url": "storage/..." }
    ```

- **[PUT]** `/api/kyc/documents/{id}` (multipart/form-data)
  - Body: `file` (jpeg|png|pdf, max 5MB). Postman alt: `POST` + `_method=PUT`.
  - 200: same payload as upload document.

- **[DELETE]** `/api/kyc/documents/{id}`
  - Postman alt: `POST` + `_method=DELETE`.
  - 204: empty response.

- **[POST]** `/api/kyc/selfie` (multipart/form-data)
  - Body: `image` (jpeg|png, max 5MB)
  - 200:
    ```json
    { "url": "storage/..." }
    ```

- **[POST]** `/api/kyc/submit`
  - Requires at least one uploaded document.
  - 200:
    ```json
    { "status": "success", "message": "KYC soumis, en attente de validation." }
    ```

- **[GET]** `/api/kyc/decision`
  - 200:
    ```json
    { "status": "pending|approved|rejected", "submitted": true, "rejection_reason": null }
    ```

Notes:
- Admin users cannot upload/submit KYC: server returns 403 `{ "status": "forbidden" }`.
- Common headers: `Authorization: Bearer <token>`, `Accept: application/json`.
- Validation errors return 422; storage/unknown errors return 500.

### Cagnottes API

Endpoints:

- Public list: `GET /api/cagnottes?categorie=...&q=...` (only published)
- Public detail: `GET /api/cagnottes/{id}`
- My list (auth): `GET /api/mes-cagnottes?categorie=...&published=true|false&preview=true|false&q=...`
- Create (auth): `POST /api/cagnottes` (multipart, optional `photos[]`)
- Update/Delete (auth, owner): `PUT/PATCH/DELETE /api/cagnottes/{id}`
- Add photos (auth, owner): `POST /api/cagnottes/{id}/photos` (multipart `photos[]`)
- Remove photo (auth, owner): `DELETE /api/cagnottes/{id}/photos` { path }
- Preview/Unpreview (auth, owner): `POST /api/cagnottes/{id}/preview` | `POST /api/cagnottes/{id}/unpreview`
- Publish/Unpublish (auth, owner): `POST /api/cagnottes/{id}/publish` | `POST /api/cagnottes/{id}/unpublish`

## 6) Headers & Auth from React

- Attach `Authorization: Bearer <token>` to all protected requests.
- For file uploads, use `multipart/form-data`.

## 7) Notes

- CORS configured in `config/cors.php` (uses `CORS_ALLOW_ORIGINS`).
- Queues: ensure `queue:work` is running in dev/prod.
- Twilio and Mail providers should be configured in prod.
