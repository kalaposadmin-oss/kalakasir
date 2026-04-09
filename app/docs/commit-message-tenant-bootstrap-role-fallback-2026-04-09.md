fix(app): repair tenant owner role fallback

- rebuild missing user role assignments from legacy role data
- promote first tenant user to owner when no owner assignment exists
- keep onboarding owner bootstrap covered by focused tests
