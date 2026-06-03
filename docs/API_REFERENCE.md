# API Reference

**Version:** 0.1.0-beta
**Base URL:** `https://your-server:2087/api/v1/`
**Auth:** Bearer token in `Authorization` header

## Authentication

All endpoints require a valid API token. Create tokens via artisan:

```bash
cd /usr/local/openpanel
php artisan tinker --execute="
\$t = new \App\Models\ApiToken();
\$t->name = 'my-token';
\$t->token = hash('sha256', 'your-secret-value');
\$t->scopes = ['admin:all'];
\$t->is_active = true;
\$t->save();
echo 'Token: your-secret-value';
"
```

## Health

```
GET /api/v1/health
```

Returns `{"success":true,"status":"ok","version":"0.1.0-beta"}`

## Accounts

### Create Account
```
POST /api/v1/accounts/create
{
    "username": "myuser",
    "domain": "example.com",
    "email": "admin@example.com",
    "package": "default",
    "password": "SecurePass123!"
}
```

### Get Account
```
GET /api/v1/accounts/{username}
```

### Suspend Account
```
POST /api/v1/accounts/{username}/suspend
{"reason": "policy violation"}
```

### Unsuspend Account
```
POST /api/v1/accounts/{username}/unsuspend
```

### Terminate Account
```
POST /api/v1/accounts/{username}/terminate
{"confirm": true}
```

### Resource Limits
```
GET /api/v1/accounts/{username}/resource-limits
```

## WordPress

### Install WordPress
```
POST /api/v1/wordpress/install
{
    "user_account_id": 1,
    "domain": "example.com",
    "site_title": "My Site",
    "admin_user": "admin",
    "admin_password": "AdminPass123!",
    "admin_email": "admin@example.com"
}
```

### Enable Redis Object Cache
```
POST /api/v1/wordpress/enable-redis
{
    "site_id": 1,
    "domain": "example.com"
}
```

## Abuse Monitor

```
GET /api/v1/abuse-monitor
```

Returns abuse alerts for all accounts.

## Web Stack

```
GET /api/v1/web-stack
```

Returns active web stack and service statuses.

## Error Responses

| Status | Meaning |
|--------|---------|
| 200 | Success |
| 201 | Created |
| 401 | Unauthorized (missing/invalid token) |
| 403 | Forbidden (insufficient scope) |
| 404 | Not found |
| 409 | Conflict (already exists) |
| 422 | Validation error |
| 500 | Server error |

## Scopes

- `admin:all` — full access to all endpoints
- `*` — wildcard, same as admin:all
- `accounts:create`, `accounts:read`, `accounts:update`, `accounts:delete`
- `wordpress:install`, `wordpress:manage`
- `abuse:read`
