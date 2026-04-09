# Commit Message Checkout Discount Foundation 2026-04-09

```text
feat(app): add checkout discount calculation foundation

Add the foundation for calculating, storing, and reading checkout discount
breakdown data.

- add item and transaction discount calculation service
- persist discount breakdown rows during checkout
- expose persisted discounts in sale detail responses
- include focused checkout and discount calculation coverage

Validated with focused discount and sale detail tests.
```