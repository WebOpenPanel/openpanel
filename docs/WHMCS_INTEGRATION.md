# WHMCS Integration

**Version:** 0.1.0-beta
**Status:** dry-run verified (no live WHMCS tested)

## Overview

OpenPanel provides a WHMCS-compatible API for automated hosting account provisioning. The WHMCS module creates, suspends, unsuspends, and terminates hosting accounts via the OpenPanel REST API.

## Setup

### 1. Create API Token

```bash
cd /usr/local/openpanel
php artisan tinker --execute="
\$t = new \App\Models\ApiToken();
\$t->name = 'whmcs';
\$t->token = hash('sha256', 'your-secure-token-here');
\$t->scopes = ['admin:all'];
\$t->is_active = true;
\$t->save();
echo 'Token: your-secure-token-here';
"
```

### 2. Install WHMCS Module

Copy `whmcs-module/OpenPanel/` to your WHMCS `modules/servers/` directory.

### 3. Configure Server in WHMCS

1. Go to Setup → Products/Services → Servers
2. Add new server:
   - **Name:** OpenPanel
   - **Hostname:** your-server-ip
   - **Type:** OpenPanel
   - **Username:** (leave blank)
   - **Password:** your-secure-token-here
   - **Secure:** Yes
   - **Port:** 2087

### 4. Configure Product

1. Create or edit a product
2. Module Settings → Module Name: OpenPanel
3. Set default package name: `default`

## Supported Operations

| Operation | WHMCS Action | API Endpoint |
|-----------|-------------|--------------|
| Create | CreateAccount | POST /api/v1/accounts/create |
| Suspend | SuspendAccount | POST /api/v1/accounts/{username}/suspend |
| Unsuspend | UnsuspendAccount | POST /api/v1/accounts/{username}/unsuspend |
| Terminate | TerminateAccount | POST /api/v1/accounts/{username}/terminate |
| Change Password | ChangePassword | (not yet implemented) |

## Dry-Run Test Results

The WHMCS module has been validated with dry-run testing:
- Account creation flow: verified
- Suspend/unsuspend cycle: verified
- Terminate with cleanup: verified
- Error handling: verified
- No live WHMCS server was used

## Limitations

- No live WHMCS integration tested yet
- Change password not implemented
- No package sync between WHMCS and OpenPanel
- No bandwidth/disk usage reporting to WHMCS
