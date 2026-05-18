## COGS Aggregate-Only — Final Audit Summary

PR ini menyelesaikan perbaikan utama pada sistem perhitungan COGS dengan pendekatan aggregate-only dan menambahkan mekanisme validasi serta fail-loud behavior.

### Summary of Changes

- Introduced aggregate-based COGS via `sale_cost_components`
- Enforced strict reader (no fallback to snapshot)
- Added validation (`sales-cost:validate`) and rebuild (`sales-cost:rebuild`)
- Implemented fail-loud guards for:
	- missing aggregates
	- header vs component mismatch

### Validation Coverage

- Sales (normal & BOM)
- Refund & reversal
- Void & inventory restoration
- FIFO vs Average isolation
- Report consistency (finance, analytics, dashboard)

All tested scenarios passed in local simulated validation (SQLite).

### Key Improvements

- Eliminates silent financial inconsistencies
- Ensures deterministic COGS calculation
- Forces visibility of data issues via explicit errors
- Prevents fallback to legacy costing paths

### Current Status

- Engineering readiness: **~90%**
- Local validation: **PASSED**
- Production data validation: **NOT YET VERIFIED**

### Known Limitations

- No validation against real production dataset
- No high-volume / concurrency validation
- Historical edge cases not fully proven

### Risk Assessment

- Engineering risk: **medium-low**
- Financial risk: **still present until production validation**

### Go-Live Recommendation

- Do **NOT** proceed to full production rollout yet
- Recommended next step:
	1. Run `sales-cost:validate` on rollout DB
	2. Run `sales-cost:rebuild` if needed
	3. Verify cross-report consistency
	4. Monitor strict reader errors

### Practical Recommendation

Proceed with:

- controlled pilot / staged rollout
- active monitoring enabled

---

**Conclusion:**
System is now structurally sound and fail-safe, but final financial correctness must still be proven on real production data before full go-live.
