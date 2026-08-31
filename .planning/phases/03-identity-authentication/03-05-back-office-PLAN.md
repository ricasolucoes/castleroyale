---
wave: 4
depends_on: ["03-01", "03-02", "03-03"]
files_modified:
  - apps/api/composer.json
  - apps/api/app/Filament/Resources/AccountResource.php
  - apps/api/app/Filament/Resources/DeviceSessionResource.php
  - tests/Feature/Backoffice/AccountManagementTest.php
autonomous: true
---

# Plan 03-05: Back office with audited game-master tooling and moderation

## Goal
Implement a Filament-based back office to provide game-master tooling for managing accounts and device sessions, addressing the moderation requirement.

## Requirements
- REQ-14

## Context
- The back office must allow game masters to view and moderate player accounts.
- This includes viewing device sessions to detect ban evasion.

## Tasks

<task>
<read_first>
- apps/api/composer.json
- apps/api/modules/Identity/Domain/Account.php
</read_first>
<action>
Create `apps/api/app/Filament/Resources/AccountResource.php`.
Configure it to list accounts, search by email/ID, and display the `shield_expires_at` and `is_guest` status.
Add a custom action to manually adjust the beginner shield duration for moderation purposes.
The custom action MUST write an audit log entry (using Laravel's Log facade or an `audit_logs` table if present) recording the Game Master's ID, the action taken, and the before/after values of the shield.
</action>
<acceptance_criteria>
- `AccountResource.php` exists and extends Filament's `Resource`
- The resource defines a table with `email` and `shield_expires_at` columns
- The shield adjustment action explicitly logs the before/after values and actor
</acceptance_criteria>
</task>

<task>
<read_first>
- apps/api/modules/Identity/Domain/DeviceSession.php
</read_first>
<action>
Create `apps/api/app/Filament/Resources/DeviceSessionResource.php`.
Configure it to list device sessions, with relation to `Account`.
Add functionality to manually revoke a device session from the back office (sets `revoked_at`).
The revocation action MUST write an audit log entry recording the Game Master's ID, the device ID, and the revocation timestamp.
</action>
<acceptance_criteria>
- `DeviceSessionResource.php` exists
- The resource includes a revocation action
- The revocation action explicitly logs the action and actor
</acceptance_criteria>
</task>

<task>
<read_first>
- tests/Feature/Backoffice/AccountManagementTest.php
</read_first>
<action>
Write `tests/Feature/Backoffice/AccountManagementTest.php`.
Assert that back office users can view accounts and trigger the device revocation and shield adjustment actions.
Assert that triggering these actions successfully writes the expected audit log entries.
</action>
<acceptance_criteria>
- `AccountManagementTest.php` exists
- Tests assert that the Filament pages load successfully
- Tests assert that audit logs are recorded for moderation actions
- `./vendor/bin/pest --filter AccountManagementTest` exits 0
</acceptance_criteria>
</task>

## Verification
- `./vendor/bin/phpstan analyse --memory-limit=1G`
- `./vendor/bin/pest --filter Backoffice`

## Must Haves
- Game masters can view player accounts and devices.
- Game masters can revoke sessions from the UI.
- All game master mutating actions are audit logged.
