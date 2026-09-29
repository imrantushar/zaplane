# ZenCommunity Core + Pro / Zaplane — Release validation
Date: 2026-09-24
Local root: C:\Users\dell\Local Sites\kodezen\app\public\wp-content\plugins\zaplane

## Implemented scope
- 32 triggers and 34 actions corresponding to installed ZenCommunity Core + Pro capabilities.
- 66 saved coverage workflows, all left non-active (62 paused, 4 draft).
- Additional persisted QA workflows for negative error-route and member-triggered admin message, both paused.
- Dynamic field selectors: 9 of 9 passed.
## Verified execution
- Action workflows: 30 passed with saved real runs; 4 destructive workflows passed inside real InnoDB transactions and rolled back.
- Trigger workflows: 27 passed via synthetic WordPress hook -> Zaplane run -> notification; 5 destructive lifecycle triggers also passed through actual ZenCommunity model operations -> Zaplane -> notification in rolled-back transactions.
- The 5 destructive trigger fixtures had also passed isolated resolver tests.
- Admin-owned workflow triggered by a regular member, then executed without a logged-in WordPress session: passed, with WordPress user context restored.
- Member-triggered admin-authored private message: passed; persisted sender/recipient checked.
- Negative-chain regression: invalid recipient makes the action node and run failed; downstream action does not run.
- PHP syntax checks and git diff --check passed for reviewed integration and QA files.
- HTTP GET /wp-json/ returned 200; Zaplane REST namespace advertised. Unauthenticated workflows route returned 401 as expected.
## Data preservation / release notes
- No existing QA post/comment/membership/RSVP permanently removed; every destructive test verified rollback.
- No GitHub commit or push.
- Optional php_imagick.dll is missing from this Windows CLI PHP configuration. It emits a startup warning, independent of the ZenCommunity integration.
- Original normal full WordPress CLI smoke script stalled and was terminated. The limited-plugin WordPress bootstrap, workflow API, all above tests and the site's HTTP REST discovery completed. Full UI/browser walkthrough and a clean-install package test were not performed.
- A number of trigger tests used deliberately synthetic hook payloads rather than the community UI; model-origin destructive tests were executed as described above. Do not interpret coverage as a guarantee against every environment/plugin-version variation.
- The original coverage-report.md predates the final transactional tests; use THIS file for final status.
