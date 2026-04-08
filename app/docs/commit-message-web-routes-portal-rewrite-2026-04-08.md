# Commit Message Web Routes Portal Rewrite 2026-04-08

## Subject

`refactor(web): move portal and detail page route flow into controllers`

## Alternative Subject

`refactor(routes): slim web routes and extract portal page handlers`

## Commit Body

```text
Slim down routes/web.php by moving remaining portal and detail-page
closures into dedicated web controllers.

- extract POS page flow into PosPageController
- extract backoffice detail page flow into BackofficePageController
- move platform dashboard gate and impersonation expiry handling into PlatformDashboardController
- keep legacy redirect and portal behavior unchanged while making routes declarative

Validated with focused portal, operational page, and platform dashboard web tests.
```

## PR Title

`Slim web routes by moving portal and detail page flow into dedicated web controllers`

## PR Description Source

Gunakan isi dari:

- `docs/pr-summary-web-routes-portal-rewrite-2026-04-08.md`