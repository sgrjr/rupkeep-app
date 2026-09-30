# Authorization proof-of-concept tests (2026-09-30 go-live audit)

These four files **prove vulnerabilities**: each test passes against the code as of
2026-09-30, and each one should *fail* once the corresponding fix ships.

They are deliberately outside `tests/Feature` so `php artisan test` does not run
them. Run one directly:

```
php artisan test tests/Audit/2026-09-30-authz-poc/PocTest.php
```

| File | Proves | Task |
|------|--------|------|
| `PocTest.php` | Customer rewrites another org's admin (email + password) and promotes self; any user creates an org | TASK-428, TASK-432 |
| `Poc2Test.php` | Customer calls `OrganizationShow::createUser` (admin) and `deleteJobs`; org-A admin force-deletes org-B invoice; customer invoices org-B job | TASK-429, TASK-430, TASK-431 |
| `Poc3Test.php` | Customer opens `/my/jobs/{id}` and `/my/customers/{id}` | TASK-434 |
| `Poc4Test.php` | Customer lists all org invoices, downloads and deletes attachments, sends invoice email; driver generates an invoice | TASK-434, TASK-433, TASK-438 |

TASK-442 is the job of inverting these into permanent regression tests under
`tests/Feature/` and then deleting this directory.
