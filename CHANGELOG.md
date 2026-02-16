# Changelog

All notable changes to this project are documented in this file.

## v0.3.0 - 2026-02-16

### Added
- Recurring appointment series domain (`/appointments/series` CRUD/status).
- Hybrid reminder queue domain (`/reminders` list/open/mark-sent/cancel).
- Reminder scheduler command (`reminders:mark-missed`) every 5 minutes.
- Appointment resource fields for series/reminder visibility.

### Changed
- WhatsApp flow is now appointment/reminder scoped.
- Students list default status behavior aligned to `all` when omitted.
- API and route matrix docs expanded for series/reminders.

### Fixed
- User list search/sort feature test stabilized with deterministic search seed.

### Known Limitations
- No direct WhatsApp provider integration in this release (manual confirmation flow).
- Reminder delivery receipts from provider are not available in this release.

