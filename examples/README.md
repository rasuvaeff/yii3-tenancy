# Examples

| Script | Shows | Needs server? |
|--------|-------|:-------------:|
| [`resolve-tenant.php`](resolve-tenant.php) | Header/subdomain resolution through `TenantResolutionMiddleware`, request attribute, 404/403 policies | no |

Run from the package root (after `composer install`):

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 php examples/resolve-tenant.php
```
