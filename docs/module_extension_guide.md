# Module Extension Guide

This guide explains the manual steps required when adding new functions or introducing new fields in each core HireMe module. Follow these module-specific checklists to ensure code consistency, proper data handling, and compliance with security and moderation workflows.

## 1. User Management & Authentication Module

1. **Define the feature scope**  
   - Clarify whether the new function belongs in `AuthController`, a service under `app/Services/Auth`, or a related helper.  
   - Confirm the models impacted (`User`, `Session`, `PasswordResetToken`, etc.).
2. **Update routing**  
   - Register new HTTP endpoints in `public/index.php` (or the router configuration) with the correct HTTP verbs.  
   - Add route middleware for authentication or throttling if required.
3. **Modify models**  
   - When introducing new fields, add columns via a migration or schema update in `database/migrations`.  
   - Update `$fillable`, `$hidden`, `$casts`, and validation rules in `app/Models/User.php` (and related models) to reflect new attributes.  
   - Add or update relationships if the new field references another table.
4. **Adjust services & helpers**  
   - Extend authentication services to include the new logic (e.g., token generation, multifactor checks).  
   - Update password hashing, session handling, or audit logging if new fields affect these processes.
5. **Revise controllers**  
   - Add new controller methods or extend existing ones to call the service layer.  
   - Validate incoming request data with the updated rules.  
   - Return appropriate view data or JSON responses.
6. **Update views and forms**  
   - Modify templates in `app/Views/auth/` to include new input fields or display updated user data.  
   - Ensure CSRF tokens and input sanitization are applied.
7. **Security verification**  
   - Review `Security.md` and existing middleware for compliance (password storage, brute-force protection, rate limiting).  
   - Add or update unit tests covering new authentication paths.  
   - Confirm logging and auditing capture the new activity where relevant.

## 2. Resume & Profile Management Module

1. **Identify models and relations**  
   - Determine whether the change affects `Candidate`, `Resume`, `Skill`, or profile-related models.  
   - Update relationships (e.g., hasMany, belongsToMany) and pivot tables as required.
2. **Schema updates**  
   - Introduce new resume/profile fields through migrations.  
   - Set defaults and constraints consistent with business rules (e.g., nullable fields for optional profile data).
3. **Service layer enhancements**  
   - Update services under `app/Services/Profile` to process new fields (validation, formatting, serialization).  
   - Ensure search indexing or filtering logic accounts for the new attributes.
4. **Controller changes**  
   - Extend `CandidateController`/`EmployerController` to accept and persist new data via the service layer.  
   - Handle file uploads or media references if adding attachments (e.g., portfolios).
5. **View updates**  
   - Modify templates in `app/Views/profile/` and any shared partials to collect and display new fields.  
   - Provide client-side validation hints when necessary.
6. **API exposure**  
   - If the new field should be accessible through `/api/users/{type}` endpoints, update the API serializers and transformers.  
   - Document the new field in `api.md` and ensure responses include it when authorized.
7. **Testing & data integrity**  
   - Add unit and integration tests covering profile updates and resume rendering.  
   - Seed example data in factories or fixtures for QA environments.  
   - Verify privacy controls for sensitive profile information.

## 3. Job Posting & Application Module

1. **Model considerations**  
   - Update `JobPosting`, `Application`, and related models to include new fields, ensuring fillable properties and casts are accurate.  
   - Adjust relationships (e.g., job-to-company, job-to-skills) when new fields introduce dependencies.
2. **Database migrations**  
   - Create migrations for new columns or tables (e.g., job benefits, application stages).  
   - Index frequently queried fields to maintain performance.
3. **Controller & service logic**  
   - Extend `JobController` and `ApplicationController` to handle new business logic, validations, and notifications.  
   - Update workflow services (publishing, application review pipelines) to incorporate the new attributes.  
   - Check moderation hooks (`AdminModerationService`) for required updates.
4. **View and form updates**  
   - Adjust `app/Views/jobs/` and `app/Views/applications/` to collect, display, and validate new job fields or application steps.  
   - Provide conditional UI elements when fields are employer-only or candidate-only.
5. **Notification & communication flows**  
   - Update email templates, in-app messages, or webhook payloads to include the new data.  
   - Ensure new actions trigger notifications through existing channels (email, dashboards).
6. **API & search**  
   - Reflect new job attributes in public/private API responses.  
   - Update search indexing, filters, and sorting logic to accommodate the added fields.
7. **Testing**  
   - Add functional tests covering job creation/editing and application submission with the new fields.  
   - Validate that existing workflows (drafts, approvals, status changes) still pass.

## 4. Payment & Billing Module

1. **Regulatory and compliance review**  
   - Confirm new functionality aligns with `Security.md` and payment industry requirements (PCI DSS).  
   - Determine if legal or finance approvals are required before implementation.
2. **Model & schema updates**  
   - Modify `Payment`, `Invoice`, or `Subscription` models and migrations for new monetary fields or payment states.  
   - Ensure proper data types (`decimal` with fixed precision) and currency handling.
3. **Integration services**  
   - Update payment gateways (`StripePayment`, etc.) to process new operations or metadata.  
   - Adjust webhook handlers to parse and verify additional payload data.
4. **Controller logic**  
   - Extend `PaymentController` to validate requests, manage retries, and generate receipts for new actions.  
   - Ensure error handling covers gateway failures and reconciliation steps.
5. **Billing UI & receipts**  
   - Update views under `app/Views/payments/` to capture new billing details or display transaction history.  
   - Modify PDF/HTML receipt templates if invoice layouts change.
6. **Accounting & reporting**  
   - Adjust reporting jobs or exports (e.g., to CSV) to include the new fields.  
   - Update scheduled tasks or cron jobs responsible for billing cycles.
7. **Testing & monitoring**  
   - Write unit/integration tests that mock payment gateways to cover success, failure, and edge cases.  
   - Verify logs and metrics capture anomalies; update alerts if new events are introduced.

## 5. Administration & Moderation Module

1. **Policy alignment**  
   - Review moderation policies documented in `admin_integration_tasks.md` before introducing new actions.  
   - Confirm guardian/arbiter workflows still enforce compliance.
2. **Model & data changes**  
   - Update admin-specific models (e.g., `Report`, `ModerationLog`) and migrations when storing new fields.  
   - Ensure auditing fields (timestamps, actor IDs) are maintained.
3. **Service layer updates**  
   - Extend `AdminModerationService` or related services to process new moderation rules or escalation paths.  
   - Update event subscribers and webhooks to broadcast additional signals to other modules.
4. **Controller & dashboard adjustments**  
   - Modify `AdminController` endpoints and dashboard widgets to surface new data.  
   - Enforce permission checks (role/ability) via middleware or policies.
5. **UI considerations**  
   - Update admin views under `app/Views/admin/` with new controls, filters, or status displays.  
   - Provide clear audit trails and tooltips for moderators.
6. **Notifications & integrations**  
   - Ensure new moderation actions trigger notifications to affected modules (Users, Jobs, Payments).  
   - Update API endpoints if third-party integrations consume moderation data.
7. **Testing & logging**  
   - Add regression tests for moderation flows, including edge cases (e.g., false positives).  
   - Confirm logs capture actor, action, and context for forensic review.  
   - Review rate limits and throttles for new admin APIs.

## General Best Practices

- **Documentation**: Update `api.md`, user-facing help guides, and inline code comments after adding new functionality.  
- **Code Style**: Follow existing naming conventions and PSR standards outlined in `DesignPattern.md` and `ModelORM.md`.  
- **Security**: Re-run security checklists in `Security.md` after any sensitive change.  
- **Testing**: Execute automated test suites in `tests/` and add new coverage where gaps exist.  
- **Deployment**: Coordinate schema changes with DevOps, apply migrations, and monitor logs post-release.
