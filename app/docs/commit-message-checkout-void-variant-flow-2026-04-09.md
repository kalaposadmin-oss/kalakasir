feat(app): add variant-safe checkout and void runtime flow

- consume recipe raw materials from exact variant buckets on checkout
- reverse sale and recipe ledger entries into the right variant buckets on void
- carry request ids into sale completion and void audit events
