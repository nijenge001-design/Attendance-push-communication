# Attendance Device Management API – Laravel 13 + Native JSON:API

## Access model

| Client | Auth | Scope |
|--------|------|-------|
| Admin / Manager / Operator / Viewer | Sanctum Bearer token | **Only sites they belong to** (Admin = all) |
| Integration (ERP) | Sanctum token (`role=integration`) | Sites assigned to that integration user |
| Attendance device | Serial (`?SN=...`) | Single device (must be approved) |

---

## Roles & permissions

| Ability | Admin | Manager | Operator | Viewer | Integration |
|---------|:-----:|:-------:|:--------:|:------:|:-----------:|
| Manage devices | ✅ | ✅ | ✅ | ❌ | ❌ |
| Approve / block devices | ✅ | ✅ | ✅ | ❌ | ❌ |
| Manage employees (add/update) | ✅ | ✅ | ✅ | ❌ | ✅ |
| **Read employees** | ✅ | ✅ | ✅ | ✅ | ✅ |
| Delete employees | ✅ | ✅ | ❌ | ❌ | ❌ |
| Manage sites | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Manage users** | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Manage commands** | ✅ | ❌ | ✅ | ❌ | ❌ |
| Resolve import errors | ✅ | ✅ | ✅ | ❌ | ❌ |
| Read attendance | ✅ | ✅ | ✅ | ✅ | ✅ |

### Notes

- **Manager** cannot manage users or device commands.
- **Viewer** can read employees and attendance (site-scoped).
- **Integration** can add/update employees and read attendance (for ERP sync).
- If an Integration needs *global* access: assign it to all sites, or use an Admin token for that system.
- **Every non-admin user only sees data for sites linked in `user_site`.**

---

## Site membership

```php
// Assign user to a site
$user->sites()->syncWithoutDetaching([$siteId => ['is_active' => true]]);

// Check
$user->hasAccessToSite($siteId);
$user->accessibleSiteIds(); // null for admin = unrestricted
```

List endpoints automatically filter by the authenticated user’s sites.

---

## ERP example

```http
POST /api/v1/attendees
Authorization: Bearer <integration-token>
```

```http
GET /api/v1/attendance-logs?filter[start]=2026-09-01&filter[end]=2026-09-08
Authorization: Bearer <integration-token>
```

---

## Device protocol

Hardware uses `/iclock/*` with serial authentication — no user tokens.

---

## Same role, different access

Role sets the **defaults**. Per-user rows in `user_permission` override them.

```php
// Two Operators – one can manage commands, one cannot
$opA->denyPermission(Permission::ManageCommands);
$opB->grantPermission(Permission::ManageCommands); // already default, no-op

// Integration that needs device approval too
$erp->grantPermission(Permission::ApproveDevices);

// Viewer that must not see employees
$viewer->denyPermission(Permission::ReadEmployees);

// Clear override → fall back to role default
$user->clearPermissionOverride(Permission::ManageCommands);

// Check
$user->hasPermission(Permission::ManageEmployees);
$user->canManageEmployees(); // same thing
```

Resolution order:

1. **Admin** → always allowed
2. **User override** (`user_permission`) if present
3. **Role default** (`Permission::defaultsFor($role)`)
