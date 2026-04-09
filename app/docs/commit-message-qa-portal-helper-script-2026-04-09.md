# Commit Message QA Portal Helper Script 2026-04-09

## Subject

`chore(app): add portal qa user helper script`

## Body

```text
Add a small dev helper that ensures portal QA users exist locally with the
expected owner and cashier roles.

- bootstrap Laravel from a standalone script
- create or update QA owner and cashier portal users
- assign roles through the default role setup service

Validation:
- php scripts/dev/ensure_portal_qa_users.php
```