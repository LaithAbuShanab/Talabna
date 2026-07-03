# API Authentication & Account Endpoints (`/api/v1`)

Everything in this document lives under the `/api/v1` prefix
(`restaurant-backend/routes/api_v1.php`, included from `routes/api.php`).
See `docs/API_CONVENTIONS.md` for the response envelope, versioning policy,
and general conventions these endpoints follow.

Not implemented (explicitly out of scope for this round): social login,
OTP/one-time-code login or verification.

## Authentication model

- **Laravel Sanctum**, bearer tokens only — no cookie/session (SPA) auth.
  Every authenticated request sends `Authorization: Bearer <token>`.
- Tokens are **named after the device** that requested them (`device_name` in
  the request body), so a user can see/manage which of their devices are
  logged in (`personal_access_tokens.name`).
- A user can hold multiple tokens at once (one per device). Logging in again
  from the same device name does not revoke other tokens — it just issues a
  new one.

## Response envelope

All endpoints below use the platform's standard envelope
(`App\Http\Responses\ApiResponse`):

```json
{ "success": true, "message": "...", "data": {} }
```

```json
{ "success": false, "message": "...", "errors": {} }
```

Validation errors return `422` with per-field messages in `errors`,
authentication failures `401`, authorization failures `403` — all rendered
automatically by `bootstrap/app.php`, not by these controllers.

## Bilingual validation

Every validation message (missing field, invalid email, unique email taken,
current password mismatch, etc.) is returned in **Arabic or English**
depending on the request's resolved locale (`APP_LOCALE=ar` by default, with
`en` as fallback) — see `lang/{ar,en}/{validation,auth,passwords,address}.php`.

---

## Auth endpoints

### `POST /api/v1/auth/register`

Public. Creates a new customer account (role is always `customer` —
never settable by the client) and immediately issues a token for the given
device.

| Field | Rules |
|---|---|
| `name` | required, string, max 255 |
| `email` | required, email, max 255, unique |
| `password` | required, confirmed (`password_confirmation`), min 8, letters + numbers |
| `device_name` | required, string, max 255 |

Response `201`:
```json
{ "success": true, "message": "...", "data": { "user": {...}, "token": "1|xxxx..." } }
```

### `POST /api/v1/auth/login`

Public. **Rate limited**: 5 attempts/minute, keyed by `email + IP` (see
"Rate limiting" below).

| Field | Rules |
|---|---|
| `email` | required, email |
| `password` | required |
| `device_name` | required, string, max 255 |

Wrong email or password both return the same generic `422` validation error
on the `email` field (`auth.failed`) — does not reveal which one was wrong.
Response `200` on success, same shape as register.

A **blocked** account (`users.is_active = false`, see `docs/ADMIN_CUSTOMERS.md`)
fails login with a distinct `422` on `email` (`auth.account_blocked`),
checked *after* the password succeeds — the customer already proved they
own the credentials, so this isn't an enumeration risk, and is far more
useful than a generic failure. Every other authenticated route also
rejects a blocked account's token via the `ensure.active` middleware
(`App\Http\Middleware\EnsureAccountIsActive`) — `logout`/
`logout-all-devices` are the one deliberate exception (see
`docs/ADMIN_CUSTOMERS.md`).

### `POST /api/v1/auth/logout`

Requires `auth:sanctum`. Revokes **only the token used for this request**
(the current device). Other devices stay logged in.

### `POST /api/v1/auth/logout-all-devices`

Requires `auth:sanctum`. Revokes **every** token belonging to the user —
all devices are logged out, including the one making this request.

### `POST /api/v1/auth/forgot-password`

Public. **Rate limited**: 3 attempts/minute, keyed by `email + IP`.

| Field | Rules |
|---|---|
| `email` | required, email |

**Always returns the same `200` response** regardless of whether the email
belongs to a real account — this endpoint cannot be used to enumerate
registered users. Internally calls `Password::sendResetLink()` but
deliberately ignores its return value.

If the account exists, a reset token is emailed via
`App\Notifications\ApiResetPasswordNotification` — the **raw token itself**
(not a web link, since this backend has no reset-password web page for a
link to point to; this is not an OTP, just Laravel's standard reset token
delivered differently). The client collects this token from the email and
submits it to `reset-password` below.

### `POST /api/v1/auth/reset-password`

Public.

| Field | Rules |
|---|---|
| `token` | required, string (from the forgot-password email) |
| `email` | required, email |
| `password` | required, confirmed, min 8, letters + numbers |

On success: password is updated, **every existing token for that user is
revoked** (all devices must log in again with the new password), and the
standard Laravel `PasswordReset` event fires. On an invalid/expired token,
returns `422` with the reason (`passwords.token`).

---

## Profile endpoints

All require `auth:sanctum`.

### `GET /api/v1/profile`

Returns the authenticated user (`UserResource`).

### `PUT /api/v1/profile`

Partial update.

| Field | Rules |
|---|---|
| `name` | sometimes, required, string, max 255 |
| `email` | sometimes, required, email, max 255, unique (ignoring the current user) |
| `phone` | sometimes, nullable, string, max 30 |

`role`/`is_active`/`blocked_reason` can never be changed through this
endpoint (not in the request rules, and not mass-assignable on `User`
regardless — see `docs/ADMIN_CUSTOMERS.md`).

### `PUT /api/v1/profile/password`

| Field | Rules |
|---|---|
| `current_password` | required, must match the account's current password (`current_password:sanctum` guard) |
| `password` | required, confirmed, min 8, letters + numbers |

On success, every **other** device's token is revoked (the token used to
make this request is preserved, so the current session stays logged in).
Wrong `current_password` returns `422` on that field.

---

## Address endpoints

All require `auth:sanctum`. Every operation is scoped to **the authenticated
user's own addresses** — attempting to view/update/delete/set-default
another user's address returns `403` (`App\Policies\CustomerAddressPolicy`,
checked via the Form Request's `authorize()` for updates and via
`Gate::authorize()` for delete/set-default, since those have no body to
validate).

### `GET /api/v1/addresses`

Lists only the authenticated user's addresses (`CustomerAddressResource`
collection), most recent first.

### `POST /api/v1/addresses`

| Field | Rules |
|---|---|
| `label` | nullable, string, max 255 |
| `address_line1` | required, string, max 255 |
| `address_line2` | nullable, string, max 255 |
| `city` | required, string, max 255 |
| `latitude` | nullable, numeric, between -90 and 90 |
| `longitude` | nullable, numeric, between -180 and 180 |
| `is_default` | nullable, boolean |
| `notes` | nullable, string |

A user's **first** address is always made default automatically, regardless
of `is_default`. Setting `is_default: true` on any address unsets the
previous default (a user has at most one default address at a time).

### `PUT /api/v1/addresses/{address}`

Same fields as create, all `sometimes`. `403` if the address doesn't belong
to the authenticated user.

### `DELETE /api/v1/addresses/{address}`

Deletes the address. **Safe to delete even if linked to a past order**: the
`orders` table stores a full snapshot of the delivery address
(`delivery_address_line`, `delivery_city`, `delivery_latitude`,
`delivery_longitude`) independent of the `customer_addresses` row, and
`orders.customer_address_id` is `nullOnDelete()` — deleting an address never
changes or corrupts an existing order's recorded delivery details (see
`docs/DATABASE_SCHEMA.md`'s "Snapshotting" section, and
`CustomerAddressTest::test_deleting_an_address_linked_to_an_old_order_preserves_the_orders_snapshot`).

### `POST /api/v1/addresses/{address}/default`

Marks this address as the default, unsetting the user's previous default.

---

## Rate limiting

Configured in `App\Providers\AppServiceProvider::boot()`:

| Limiter | Applies to | Limit | Key |
|---|---|---|---|
| `login` | `POST /auth/login` | 5/minute | `email + IP` |
| `forgot-password` | `POST /auth/forgot-password` | 3/minute | `email + IP` |

Keyed by `email + IP` rather than IP alone (so one abusive IP behind a
shared NAT/proxy can't lock out every account on that IP) or email alone
(so an attacker can't lock a victim out by repeatedly failing their email
from anywhere). Exceeding the limit returns `429`.

## Testing

`restaurant-backend/tests/Feature/Api/V1/`:
`AuthTest`, `PasswordResetTest`, `ProfileTest`, `CustomerAddressTest`,
`RateLimitingTest` — cover the happy path, validation failures, cross-user
address ownership (403), token revocation semantics (single-device vs.
all-devices logout, password-change/reset revocation), the
forgot-password anti-enumeration guarantee, the address-deletion/order-snapshot
guarantee, and rate limiting on both throttled endpoints.
