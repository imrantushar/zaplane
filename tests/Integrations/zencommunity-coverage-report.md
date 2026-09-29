# ZenCommunity Core + Pro / Zaplane QA — 2026-09-24

Local project: C:\Users\dell\Local Sites\kodezen\app\public\wp-content\plugins\zaplane

- Registered and persisted: 32 trigger workflows (IDs 257–288) and 34 action workflows (IDs 289–322); no creation errors.
- Real Zaplane action workflow execution: 30/34 passed, with saved run output and QA entities.
- Synthetic WordPress hook -> live Zaplane workflow -> notification action: 27/32 triggers passed.
- Non-destructive resolver tests only: leaves_space, removed_space, space_deleted, post_deleted, comment_deleted (5/32).
- Destructive action workflows NOT live-executed: remove_user_space, delete_post, delete_comment, cancel_rsvp (4/34).
- 66 QA workflows left inactive: 62 paused, 4 draft. Pending QA nodes: 0.
- All generated customer-independent QA entities and test records retained. No GitHub push.
- PHP lint passed for zencommunity.php, query-trait.php and action/trigger coverage runners.
- Local PHP CLI reports a missing optional php_imagick.dll extension; synthetic events emitted some ZenCommunity/Pro undefined-key warnings because fixtures do not carry full upstream event payloads.
- Passing synthetic-hook tests is not a substitute for testing every event through the ZenCommunity UI or REST endpoints.

Scripts: zencommunity-coverage-matrix.php, zencommunity-coverage-execute.php, zencommunity-coverage-triggers.php, zencommunity-coverage-status.php.
