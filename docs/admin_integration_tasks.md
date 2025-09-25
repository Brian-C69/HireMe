# Admin Guardian & Arbiter Integration – Engineering Task List

The API contract now positions the Administration & Moderation module as a
platform service that every other module must consume synchronously (guardian
APIs) and asynchronously (arbiter webhooks). This document captures the work
required to ship that behaviour end-to-end.

## 1. Platform services to implement in Admin

1. **Moderation scan API** – Build `/admin/moderation/scan` to accept the payload
   defined in the IFA, call the policy engines, and return the allow/deny verdict
   within 150 ms p95. Persist review IDs for later webhook correlation.
2. **Moderation status API** – Implement `/admin/moderation/status` to return the
   latest moderation state for any target (job, profile, resume, message).
3. **User & org enforcement APIs** – Provide `/admin/enforcement/user/{id}` and
   `/admin/enforcement/org/{id}` for inline ban/suspension checks. Cache results
   with ETags so callers can reuse responses.
4. **Audit log ingestion & querying** – Accept writes via
   `POST /admin/audit/logs`, store audit entries in a central repository, and
   expose `GET /admin/audit/logs` for investigations across modules.
5. **Feature flag resolution** – Return the global flag map from `GET /admin/flags`,
   individual values from `GET /admin/flags/{key}`, and evaluate contextual
   overrides with `POST /admin/flags/evaluate`.
6. **Risk scoring API** – Expose `POST /admin/risk/score` that runs anti-fraud
   heuristics and returns `{score, advise}` results for charges, publishes, and
   authentications.
7. **Webhook dispatcher** – Emit arbiter events (`admin.user.banned`,
   `admin.job.taken_down`, `admin.profile.masked`, `admin.refund.approved`,
   `admin.flags.threshold.hit`) with payloads that downstream modules can act on.
   Provide retry with exponential backoff.
8. **Observability & SLOs** – Instrument latency metrics, webhook delivery stats,
   and failure alerts so we can enforce the ≤150 ms guardian timeout budget.

## 2. Update consumers to call guardian APIs inline

*User Management & Authentication*
- Call `/admin/enforcement/user/{id}` during login/token issuance. Block access
  when the state is not `ok`.
- Invoke `POST /admin/risk/score` for `auth.login` and `auth.register` events,
  applying MFA/step-up when the advise is `challenge`.
- Fetch `/admin/flags` to gate experiments (e.g., MFA beta) and write audit
  entries for role changes via `POST /admin/audit/logs`.
- Subscribe to `admin.user.banned` events to revoke active sessions immediately.

*Resume & Profile*
- On every profile/resume publish or update, call `POST /admin/moderation/scan`
  and honour `actions` such as `hold` or `mask` before committing changes.
- When serving public profile views, call `GET /admin/moderation/status` to
  determine whether to redact content. Record audits for masked views.
- Handle `admin.profile.masked` webhooks by updating persisted visibility flags.

*Job Posting & Application*
- Call both `POST /admin/moderation/scan` and `POST /admin/risk/score` with
  `job.publish` before jobs go live. Abort publish if `allowed=false` or
  `advise=deny`.
- Run message content through `moderation/scan` before sending recruiter ↔
  candidate chat.
- Write audit events for stage changes/interviews.
- React to `admin.job.taken_down` by delisting jobs and notifying billing.

*Payment & Billing*
- For high-value charges or refunds, invoke `POST /admin/risk/score` with
  `payment.charge` or `payment.refund` contexts and respect the advise.
- Check `/admin/enforcement/org/{orgId}` prior to invoicing or payout releases.
- Publish audit logs for invoice generation and refund approvals.
- Act on `admin.refund.approved` and `admin.user.banned` webhooks to halt payouts
  or trigger credit reversals.

## 3. Shared deliverables

1. **Module registry wiring** – Register the new Admin endpoints in
   `ModuleRegistry` and surface them through `ModuleGatewayController` so all
   modules can call them via `forward()`.
2. **SDK/helpers** – Provide a lightweight Admin client wrapper in
   `AbstractModuleService` (or a dedicated helper) to centralise request signing,
   timeout handling, and response validation.
3. **Automated tests** – Add integration tests covering guardian responses,
   webhook dispatch, and downstream reactions. Mock Admin when testing other
   modules so they exercise the new flow.
4. **Documentation** – Update `api.md` and module READMEs with the new contract,
   timing guarantees, and webhook payload shapes. Include runbooks for ops.
5. **Migration plan** – Stage rollout behind feature flags, with dual-write logs
   and shadow moderation to validate policies before enforcing hard blocks.

Delivering the above completes the transformation of Admin from a passive
reporting surface into the policy authority consumed by every module.
