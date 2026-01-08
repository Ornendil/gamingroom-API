# Gamingrom API

A small, tenant-aware API for managing gaming room time slots in public spaces
(libraries, schools, youth centers).

It is designed for staff-managed, on-site gaming rooms where fairness,
turnover, and transparency matter more than advance booking.

This API is intentionally not a booking system, but a *same-day queue and display system*.

---

## Core concepts

### Tenants

Each gaming room (or group of rooms) is a **tenant**.

* One tenant = one subdomain
  e.g. `example.gamingrom.exampledomain.no`
* One tenant = one SQLite database
* One tenant = one `tenant.json`

Tenants are isolated from each other at:

* database level
* JWT secret level
* rate-limit level
* CORS level

---

### Sessions

A **session** represents a contiguous block of time on a device.

Stored fields:

* `time_slot` – system / grid position (**authoritative**)
* `fra` / `til` – human-readable time (denormalized, for sanity & debugging)
* `navn` – display name (primary identifier)
* `lnr` – optional secondary identifier (library use)
* `computer` – device id (historical name; refers to any device)

Sessions are:

* same-day only
* automatically cleaned up by retention rules
* never historical by design

Session length is defined, in tenant.json, in grid slots, not minutes.
This keeps the admin UI consistent regardless of slot size.

---

### Devices

Devices are defined per tenant in `tenant.json`.

Each device has:

* `id` (lowercase, stable)
* `label` (for UI)
* `type` (pc / playstation / switch / etc.)

The API **does not** assume anything about device layout or grouping.

---

## Repository structure

```text
gamingroom-API/
├── tenants/
│   └── example/
│       └── tenant.json
│       └── users.json
│       └── gamingrom.db
├── public/
│   └── api/
│       ├── login/
│       ├── logout/
│       ├── refresh/
│       ├── save/
│       ├── data/
│       ├── sessions/
│       └── update/
│           └── lnr/
│           └── name/
│           └── time/
├── jwt/
├── csrf/
├── tmp/
├── config.php
├── bootstrap.php
├── apiHeaders.php
├── rateLimit.php
└── README.md
```

---

## Installation

### 1. Place the API

Deploy the API **outside the public web root**.

Only `public/` should be web-accessible (via symlink or server config).

---

### 2. Create tenants

For each tenant:

1. Create a directory in `tenants/`
2. Add a `tenant.json`
3. Ensure the web server can write to:

   * `tenants/<slug>/`
   * `tmp/`

Example `tenant.json`:

```json
{
  "slug": "example",
  "displayName": "Example Tenant",
  "retentionDays": 4,
  "allowedOrigins": [
    "https://example.gamingrom.exampledomain.no"
  ],
  "authMode": "lnr_or_name",
  "slotMinutes": 15,
  "defaultSessionSlots": 2,
  "devices": [
    { "id": "pc1", "label": "PC 1", "type": "pc" }
  ],
  "openingHours": {
    "monday": { "open": "12:00", "close": "16:00" },
    "tuesday": null
  }
}
```

---

### 3. DNS & subdomains

Each tenant is addressed via subdomain:

```text
<tenant>.gamingrom.exampledomain.no
```

Wildcard DNS (`*.gamingrom.exampledomain.no`) is recommended.

---

### 4. JWT secrets

Each tenant has its **own JWT secret**.

Secrets are:

* stored outside web root
* JWT secrets live in the tenant directory at runtime, but are not part of the repository.
* It's a file named `jwt_secret.txt` that contains a plain text string used as the secret.
  It can be anything sufficiently random.
* loaded via `bootstrap.php`

---

### 5. Users (admin access)

Admin users are defined per tenant.

Passwords are stored hashed.

JWT:

* access token: short-lived
* refresh token: HTTP-only cookie
* access tokens explicitly marked with `"token_use": "access"`

---

## Managing admin users

Admin users are **tenant-local**.

Each tenant has its own `users.json`, stored alongside `tenant.json` and the tenant database:

```text
tenants/
├── ullbib/
│   ├── tenant.json
│   ├── users.json
│   └── gamingrom.db
```

There is **no global users file**.

---

### `users.json` format

```json
{
  "bruker": {
    "password": "plaintext-or-hash"
  },
  "anotheruser": {
    "password": "$2y$10$..."
  }
}
```

* Keys are usernames
* Passwords must be stored **hashed** before the API is used
* Plaintext passwords are allowed *temporarily* during setup

---

### Hashing passwords

A tenant-aware CLI tool is provided:

```bash
php tools/hash-users.php <tenant-slug>
```

Example:

```bash
php tools/hash-users.php ullbib
```

What it does:

* hashes any plaintext passwords in `tenants/<slug>/users.json`
* skips passwords that are already hashed
* is safe to run multiple times
* never touches other tenants

If no plaintext passwords are found, the script exits without changes.

---

### Recommended workflow

```text
1. Add user with plaintext password to users.json
2. Run hash-users.php for that tenant
3. Commit users.json
4. Do not store plaintext passwords again
```

Passwords are hashed using PHP’s `password_hash()` with the default algorithm.

---

### Security notes

* Never commit JWT secrets
* Never log passwords, hashes, or tokens
* `users.json` must not be web-accessible
* Each tenant is fully isolated at the authentication level

---

## API overview

### Authenticated (admin)

Requires:

* Bearer access token
* CSRF token (for mutating requests)

Endpoints:

* `POST /api/login/`
* `POST /api/logout/`
* `POST /api/refresh/`
* `GET  /api/data/`
* `POST /api/save/`
* `POST /api/update/*`
* `POST /api/delete/`

---

### Public (display)

Unauthenticated, read-only.

* `GET /api/sessions/`

Used by:

* wall displays
* kiosks
* passive screens

CORS is restricted per tenant.

---

## Rate limiting

Rate limiting is:

* file-based
* tenant-aware
* per endpoint
* per client IP

Profiles:

* **display** – polling friendly
* **admin** – burst tolerant
* **auth** – strict

OPTIONS (CORS preflight) is never rate-limited.

On our server, Nginx provides an additional outer safety net. Hopefully yours does too.

---

## Design principles (important)

* **No historical data**
* **No personal data beyond first names**
* **No long-term identifiers**
* **No “booking” semantics**
* **No global state**

This system is meant to reflect *what is happening right now* in a physical space.

---

## Frontends

This API is intended to be used with:

* `gamingroom-admin` – staff interface
* `gamingroom-display` – public screen

They are deliberately separate applications.

---

## Known intentional limitations

* No double-booking prevention (by design, for now)
* No authentication for displays
* No analytics
* No cross-day sessions

These are conscious choices, not omissions.

---

## Future considerations (non-binding)

* optional per-device default session length
* optional IP allow-listing for displays
* optional signed display tokens
* optional conflict detection

None of these are required for current use.

---

## License

MIT