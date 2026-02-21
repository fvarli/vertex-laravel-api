# Vertex API — Architecture Documentation

> **Last updated:** 2026-02-20
> **Related project:** [Vertex React App](https://github.com/fvarli/vertex-react-app/blob/main/docs/architecture.md)

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Technology Stack](#2-technology-stack)
3. [Directory Structure](#3-directory-structure)
4. [Request Lifecycle](#4-request-lifecycle)
5. [Authentication](#5-authentication)
6. [Multi-Workspace](#6-multi-workspace)
7. [RBAC System](#7-rbac-system)
8. [Database Schema](#8-database-schema)
9. [Core Domain Modules](#9-core-domain-modules)
10. [Business Rules](#10-business-rules)
11. [Middleware Pipeline](#11-middleware-pipeline)
12. [API Response Contract](#12-api-response-contract)
13. [Audit Trail](#13-audit-trail)
14. [Localization (i18n)](#14-localization-i18n)
15. [Test Strategy](#15-test-strategy)
16. [Adding a New Feature Guide](#16-adding-a-new-feature-guide)

---

## 1. Project Overview

**Vertex** is an **API-first** platform designed to meet the needs of personal trainers for client tracking, appointment scheduling, program creation, and reminder management.

**Target audience:**

- **Gym owners / managers** — people who manage multiple trainers and clients (`owner_admin` role)
- **Trainers** — people who only see their own clients and appointments (`trainer` role)

**Architectural approach:**

- All business logic lives in this Laravel API project; the React frontend is only a consumer
- Every endpoint returns JSON; there is no HTML rendering
- **Tenant isolation** is achieved through multi-workspace support
- **Stateless** authentication via Sanctum bearer tokens

---

## 2. Technology Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Framework | Laravel | 12.x |
| Language | PHP | 8.2+ |
| Database | PostgreSQL | 15+ |
| Authentication | Laravel Sanctum | 4.x |
| API Documentation | Scramble | — |
| Testing | PHPUnit (Pest compatible) | 11.x |
| Code Style | Laravel Pint | — |
| Server | Nginx + PHP-FPM | — |

**Key composer packages:**

```
laravel/sanctum        — Bearer token authentication
dedoc/scramble         — Automatic API documentation
laravel/pint           — Code formatting
```

---

## 3. Directory Structure

```
vertex-laravel-api/
├── app/
│   ├── Exceptions/
│   │   └── AppointmentConflictException.php   # Appointment conflict exception
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       ├── BaseController.php         # Response envelope
│   │   │       └── V1/                        # All v1 controllers
│   │   │           ├── AuthController.php
│   │   │           ├── StudentController.php
│   │   │           ├── AppointmentController.php
│   │   │           ├── ProgramController.php
│   │   │           ├── ReminderController.php
│   │   │           └── ...                    # 18 controllers
│   │   ├── Middleware/                         # 8 middleware
│   │   │   ├── RequestIdMiddleware.php
│   │   │   ├── ForceJsonResponse.php
│   │   │   ├── SecurityHeadersMiddleware.php
│   │   │   ├── SetLocaleMiddleware.php
│   │   │   ├── EnsureUserIsActive.php
│   │   │   ├── EnsureWorkspaceContext.php
│   │   │   ├── EnforceIdempotencyForAppointments.php
│   │   │   └── ApiLogMiddleware.php
│   │   ├── Requests/Api/V1/                   # Form Request classes
│   │   └── Resources/                         # API Resource classes
│   ├── Models/                                # 14 Eloquent models
│   │   ├── User.php
│   │   ├── Workspace.php
│   │   ├── Student.php
│   │   ├── Program.php
│   │   ├── ProgramItem.php
│   │   ├── ProgramTemplate.php
│   │   ├── ProgramTemplateItem.php
│   │   ├── Appointment.php
│   │   ├── AppointmentSeries.php
│   │   ├── AppointmentReminder.php
│   │   ├── Role.php
│   │   ├── Permission.php
│   │   ├── AuditLog.php
│   │   └── IdempotencyKey.php
│   ├── Notifications/                         # Email notifications
│   ├── Policies/                              # 5 authorization policies
│   │   ├── StudentPolicy.php
│   │   ├── ProgramPolicy.php
│   │   ├── AppointmentPolicy.php
│   │   ├── AppointmentSeriesPolicy.php
│   │   └── AppointmentReminderPolicy.php
│   └── Services/                              # Business logic layer
│       ├── AuthService.php
│       ├── ProfileService.php
│       ├── WorkspaceContextService.php
│       ├── WorkspaceService.php
│       ├── WorkspaceTrainerService.php
│       ├── AppointmentService.php
│       ├── AppointmentSeriesService.php
│       ├── AppointmentReminderService.php
│       ├── ProgramService.php
│       ├── StudentTimelineService.php
│       ├── ReportService.php
│       ├── DashboardService.php
│       ├── DomainAuditService.php
│       ├── ApiLogService.php
│       ├── HealthService.php
│       ├── AccessContextService.php
│       └── WhatsAppLinkService.php
├── bootstrap/
│   └── app.php                                # Middleware pipeline & exception handler
├── config/
│   ├── app.php
│   ├── auth.php
│   ├── sanctum.php
│   ├── idempotency.php                        # Idempotent request TTL settings
│   ├── database.php
│   ├── logging.php
│   └── ...
├── database/
│   ├── factories/                             # Test factories
│   ├── migrations/                            # 24 migration files
│   └── seeders/
│       └── RbacSeeder.php                     # Role and permission definitions
├── docs/                                      # Project documentation
│   ├── architecture.md                        # ← This file
│   ├── production-operations-runbook.md
│   ├── release-checklist.md
│   └── smoke-qa.md
├── lang/
│   ├── en/api.php                             # English API messages
│   └── tr/api.php                             # Turkish API messages
├── routes/
│   ├── api.php                                # API prefix router
│   └── api/
│       └── v1.php                             # All v1 endpoint definitions
└── tests/
    ├── Feature/                               # Feature tests
    └── Unit/                                  # Unit tests
```

---

## 4. Request Lifecycle

The process from when an HTTP request reaches the API until a response is returned:

```
  HTTP Request
       │
       ▼
┌──────────────────────────────┐
│  Global Middleware Pipeline   │
│                              │
│  1. RequestIdMiddleware      │  ← Generate/validate X-Request-Id
│  2. ForceJsonResponse        │  ← Force Accept: application/json
│  3. ThrottleRequests (api)   │  ← Rate limiting
│  4. SecurityHeadersMiddleware│  ← Add security headers
│  5. SetLocaleMiddleware      │  ← Accept-Language → locale
└──────────────┬───────────────┘
               │
               ▼
┌──────────────────────────────┐
│  Route Middleware             │
│                              │
│  auth:sanctum                │  ← Validate bearer token
│  user.active                 │  ← Is user active?
│  workspace.context           │  ← Load workspace & determine role
│  idempotent.appointments     │  ← POST idempotency (optional)
│  api.log                     │  ← Log request/response
└──────────────┬───────────────┘
               │
               ▼
┌──────────────────────────────┐
│  Controller                   │
│                              │
│  FormRequest → validate()    │  ← Input validation
│  Policy → authorize()        │  ← Authorization check
│  Service → business logic    │  ← Domain logic
│  Model → database            │  ← Eloquent CRUD
│  Resource → transform        │  ← JSON transformation
└──────────────┬───────────────┘
               │
               ▼
┌──────────────────────────────┐
│  BaseController               │
│                              │
│  sendResponse() or           │
│  sendError()                 │  ← Envelope format
└──────────────┬───────────────┘
               │
               ▼
        JSON Response
```

**Layered architecture summary:**

| Layer | Responsibility | Example |
|-------|---------------|---------|
| Middleware | Cross-cutting concerns (auth, logging, security) | `EnsureWorkspaceContext` |
| Controller | HTTP → Service bridge, input validation | `StudentController` |
| Service | Business logic, rules, orchestration | `AppointmentService` |
| Model | Database interaction, relationships | `Appointment` |
| Policy | Authorization decisions | `StudentPolicy` |
| Resource | JSON transformation | `StudentResource` |

---

## 5. Authentication

Vertex uses bearer token-based stateless authentication with **Laravel Sanctum**.

### 5.1 Flow Diagram

```
  Register                     Login
       │                             │
       ▼                             ▼
  POST /register               POST /login
       │                             │
       ▼                             ▼
  AuthService::register()      AuthService::login()
       │                             │
       ▼                             ▼
  Create user + Generate token Authenticate + Generate token
       │                             │
       ▼                             ▼
  { user, token }              { user, token }
```

### 5.2 Token Lifecycle

```
  Token Received
       │
       ├── Every request: Authorization: Bearer <token>
       │
       ├── Refresh token: POST /refresh-token
       │   └── Old token is deleted, new token is returned
       │
       ├── Single logout: POST /logout
       │   └── Only the current token is deleted
       │
       └── Logout from all devices: POST /logout-all
           └── All of the user's tokens are deleted
```

### 5.3 Endpoints

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/register` | POST | No | New user registration |
| `/login` | POST | No | Login, returns token |
| `/forgot-password` | POST | No | Password reset email |
| `/reset-password` | POST | No | Reset password |
| `/logout` | POST | Yes | Delete current token |
| `/logout-all` | POST | Yes | Delete all tokens |
| `/refresh-token` | POST | Yes | Token refresh |
| `/email/verify/{id}/{hash}` | POST | Yes | Email verification |
| `/email/resend` | POST | Yes | Resend verification email |

### 5.4 User Model

```php
// app/Models/User.php
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'surname', 'email', 'phone',
        'avatar', 'is_active', 'system_role',
        'active_workspace_id', 'password',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
```

**`system_role` values:**

| Value | Description |
|-------|-------------|
| `platform_admin` | Platform administrator (access to all workspaces) |
| `workspace_user` | Normal user (workspace-based access) |

---

## 6. Multi-Workspace

Vertex allows the same user to belong to multiple gyms/businesses through the **workspace** model. Each workspace is an independent data space.

### 6.0 Why Multi-Workspace?

**What is a workspace?** One workspace = one gym or coaching business. A single Vertex deployment serves many fully isolated businesses from the same application instance—similar to how Shopify hosts thousands of independent shops on one platform.

**With vs Without Workspaces:**

| Concern | Without Workspaces (single-tenant) | With Workspaces (multi-tenant) |
|---------|-----------------------------------|-------------------------------|
| **Data isolation** | Separate database/deployment per gym | One database, all queries scoped by `workspace_id` |
| **User mobility** | User needs separate accounts per gym | One account, multiple workspace memberships |
| **Scaling** | Deploy & maintain N instances | One instance serves N businesses |
| **Billing** | Per-deployment cost tracking | Per-workspace usage tracking in one system |
| **GDPR / Compliance** | Data is physically separated | Logical separation — enforced by application layer |

**How it works architecturally:** Every domain entity (`Student`, `Program`, `Appointment`, etc.) carries a `workspace_id` foreign key. Every query is automatically scoped to the active workspace. Four security layers guarantee isolation:

1. **Middleware** (`EnsureWorkspaceContext`) — resolves and validates the active workspace before any controller code runs.
2. **Controller scoping** — all queries include `where('workspace_id', $workspaceId)`.
3. **Policy** — Laravel policies verify the user has the correct workspace membership and role before authorizing actions.
4. **Audit log** — workspace context is attached to every logged event for traceability.

### 6.1 Workspace Isolation Model

```
                    ┌─────────────────┐
                    │      User       │
                    │  (platform_admin│
                    │  or             │
                    │  workspace_user)│
                    └────────┬────────┘
                             │
              ┌──────────────┼──────────────┐
              │              │              │
              ▼              ▼              ▼
       ┌──────────┐  ┌──────────┐  ┌──────────┐
       │Workspace │  │Workspace │  │Workspace │
       │   "A"    │  │   "B"    │  │   "C"    │
       │ (owner)  │  │(trainer) │  │ (owner)  │
       └────┬─────┘  └────┬─────┘  └────┬─────┘
            │              │              │
        Students       Students       Students
        Programs       Programs       Programs
        Appointments   Appointments   Appointments
```

### 6.2 Pivot Table: `workspace_user`

```
workspace_user
├── user_id       (FK → users)
├── workspace_id  (FK → workspaces)
├── role          (string: 'owner_admin' | 'trainer')
├── is_active     (boolean)
└── timestamps
```

### 6.3 WorkspaceContextService

The central service that manages the active workspace and user role:

```php
// app/Services/WorkspaceContextService.php
class WorkspaceContextService
{
    public function getActiveWorkspace(User $user): Workspace
    {
        // 1. Check user.active_workspace_id
        // 2. Verify workspace exists
        // 3. Check active membership (is_active=true)
        // Throws AuthorizationException on failure
    }

    public function getRole(User $user, int $workspaceId): ?string
    {
        // Returns role from workspace_user pivot
        // 'owner_admin' | 'trainer' | null
    }

    public function isOwnerAdmin(User $user, int $workspaceId): bool
    {
        return $this->getRole($user, $workspaceId) === 'owner_admin';
    }
}
```

### 6.4 Context Middleware

The `EnsureWorkspaceContext` middleware is applied to all routes under the `workspace.context` group:

```php
// app/Http/Middleware/EnsureWorkspaceContext.php
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();
    $workspace = $this->workspaceContextService->getActiveWorkspace($user);
    $role = $this->workspaceContextService->getRole($user, $workspace->id);

    // Add to request attributes — controllers use these values
    $request->attributes->set('workspace_id', $workspace->id);
    $request->attributes->set('workspace_role', $role);

    return $next($request);
}
```

---

## 7. RBAC System

### 7.0 Two-Level Authorization Model

Vertex uses a **two-level** authorization model. Understanding this split is key to understanding every access-control decision in the codebase.

**Level 1 — System Role** (`users.system_role` column): determines platform-wide identity.

| System Role | Meaning | Permissions |
|-------------|---------|-------------|
| `platform_admin` | Vertex platform operator (think: Shopify employee) | `['*']` — full access to all workspaces and platform-level endpoints |
| `workspace_user` | Regular user (think: a gym owner or trainer) | No platform-level access; must be a member of a workspace to do anything |

**Level 2 — Workspace Role** (`workspace_user.role` pivot column): determines what a user can do *inside a specific workspace*.

| Workspace Role | Meaning | Scope |
|----------------|---------|-------|
| `owner_admin` | Gym/business owner | Full control over the workspace — manages students, programs, appointments, trainers |
| `trainer` | Trainer/coach | Sees and manages **only their own** students, programs, and appointments |

A user can hold different workspace roles in different workspaces (e.g., `owner_admin` in Workspace A, `trainer` in Workspace B).

**Concrete example:**

| User | System Role | Workspace Role | Can do |
|------|-------------|---------------|--------|
| `admin@vertex.local` | `platform_admin` | *(none needed)* | Access any workspace, manage platform settings, view all data |
| `owner@vertex.local` | `workspace_user` | `owner_admin` in "Downtown Gym" | Manage all students, programs, and trainers in Downtown Gym; **cannot** access other workspaces or platform settings |
| `trainer@vertex.local` | `workspace_user` | `trainer` in "Downtown Gym" | View/manage only their own students and appointments in Downtown Gym; **cannot** see other trainers' data or workspace settings |

**Shopify analogy:** `platform_admin` = a Shopify employee who can access the internal admin panel and see all shops. `owner_admin` = a merchant who runs their own shop on Shopify — full control of their shop, zero access to Shopify's platform internals or other merchants' shops.

### 7.1 Role and Permission Definitions

```php
// database/seeders/RbacSeeder.php
$rolePermissions = [
    'owner_admin' => [
        'workspace.manage',       // Workspace management
        'students.manage',        // Manage all students
        'programs.manage',        // Manage all programs
        'appointments.manage',    // Manage all appointments
        'calendar.view',          // Calendar viewing
    ],
    'trainer' => [
        'students.own',           // Only own students
        'programs.own',           // Only own programs
        'appointments.own',       // Only own appointments
        'calendar.view',          // Calendar viewing
    ],
];
```

### 7.2 Permission Matrix

| Permission | owner_admin | trainer |
|------------|:-----------:|:-------:|
| `workspace.manage` | **Yes** | No |
| `students.manage` | **Yes** | No |
| `students.own` | No | **Yes** |
| `programs.manage` | **Yes** | No |
| `programs.own` | No | **Yes** |
| `appointments.manage` | **Yes** | No |
| `appointments.own` | No | **Yes** |
| `calendar.view` | **Yes** | **Yes** |

### 7.3 Policy Pattern

All policies implement the same access control pattern:

```php
// app/Policies/StudentPolicy.php
class StudentPolicy
{
    public function __construct(
        private readonly WorkspaceContextService $workspaceContextService
    ) {}

    private function canAccess(User $user, int $workspaceId, int $trainerId): bool
    {
        // 1. Does the user's active workspace match?
        if ((int) $user->active_workspace_id !== $workspaceId) {
            return false;
        }

        // 2. Does the user have a role in this workspace?
        $role = $this->workspaceContextService->getRole($user, $workspaceId);
        if (! $role) {
            return false;
        }

        // 3. owner_admin can access everything
        if ($role === 'owner_admin') {
            return true;
        }

        // 4. trainer can only access their own records
        return $trainerId === $user->id;
    }
}
```

**Policies and the actions they cover:**

| Policy | Actions |
|--------|---------|
| `StudentPolicy` | view, update, setStatus |
| `ProgramPolicy` | view, update, setStatus |
| `AppointmentPolicy` | view, update, setStatus |
| `AppointmentSeriesPolicy` | view, update, setStatus |
| `AppointmentReminderPolicy` | view, update |

---

## 8. Database Schema

### 8.1 ER Diagram (Text-Based)

```
┌──────────────┐     ┌──────────────────┐     ┌───────────────┐
│    users     │────<│  workspace_user   │>────│  workspaces   │
│              │     │  (role, is_active)│     │               │
│ system_role  │     └──────────────────┘     │ owner_user_id │──┐
│ active_ws_id │──────────────────────────────│ reminder_policy│  │
└──────┬───────┘                              └───────┬───────┘  │
       │                                              │          │
       │  ┌──────────────┐                            │          │
       │  │  model_role   │     ┌─────────┐           │          │
       └─<│ workspace_id  │────>│  roles  │           │          │
          └──────────────┘     └────┬────┘           │          │
                                    │                 │          │
                            ┌───────┴───────┐        │          │
                            │role_permission │        │          │
                            └───────┬───────┘        │          │
                                    │                 │          │
                              ┌─────┴──────┐         │          │
                              │ permissions│         │          │
                              └────────────┘         │          │
                                                     │          │
       ┌─────────────────────────────────────────────┘          │
       │                                                        │
       ▼                                                        │
┌──────────────┐     ┌──────────────┐     ┌──────────────────┐  │
│   students   │────<│   programs   │────<│  program_items   │  │
│              │     │              │     │                  │  │
│ workspace_id │     │ student_id   │     │ day_of_week      │  │
│ trainer_id   │─────│ trainer_id   │     │ exercise, sets   │  │
│ status       │     │ status       │     │ reps, rest       │  │
└──────┬───────┘     └──────────────┘     └──────────────────┘  │
       │                                                        │
       │             ┌──────────────────┐                       │
       │             │program_templates │     ┌───────────────┐ │
       │             │                  │────<│ template_items│ │
       │             │ workspace_id     │     └───────────────┘ │
       │             │ trainer_id       │                       │
       │             └──────────────────┘                       │
       │                                                        │
       ▼                                                        │
┌──────────────┐     ┌────────────────────┐                     │
│ appointments │────<│appointment_reminders│                    │
│              │     │                    │                     │
│ series_id   ─┼────>│ channel, status    │                     │
│ workspace_id │     │ scheduled_for      │                     │
│ trainer_id   │     │ attempt_count      │                     │
│ student_id   │     └────────────────────┘                     │
│ status       │                                                │
└──────┬───────┘                                                │
       │                                                        │
       ▼                                                        │
┌────────────────────┐     ┌──────────────┐   ┌──────────────┐ │
│appointment_series  │     │  audit_logs  │   │idempotency_  │ │
│                    │     │              │   │   keys       │ │
│ recurrence_rule    │     │ auditable    │   │              │ │
│ status             │     │ (morphTo)    │   │ request_hash │ │
│ start_date         │     │ changes JSON │   │ expires_at   │ │
└────────────────────┘     └──────────────┘   └──────────────┘ │
```

### 8.2 Model List (14 Models)

| Model | Table | Description |
|-------|-------|-------------|
| `User` | `users` | User account, SoftDeletes |
| `Workspace` | `workspaces` | Workspace (gym/business) |
| `Student` | `students` | Student/client |
| `Program` | `programs` | Weekly training program |
| `ProgramItem` | `program_items` | Program line item (exercise) |
| `ProgramTemplate` | `program_templates` | Reusable template |
| `ProgramTemplateItem` | `program_template_items` | Template line item |
| `Appointment` | `appointments` | Appointment |
| `AppointmentSeries` | `appointment_series` | Recurring appointment series |
| `AppointmentReminder` | `appointment_reminders` | Reminder record |
| `Role` | `roles` | RBAC role |
| `Permission` | `permissions` | RBAC permission |
| `AuditLog` | `audit_logs` | Audit trail record |
| `IdempotencyKey` | `idempotency_keys` | Idempotent request record |

### 8.3 Migration Files (Chronological)

```
0001_01_01_000000_create_users_table
0001_01_01_000001_create_cache_table
0001_01_01_000002_create_jobs_table
2026_02_12_121617_create_personal_access_tokens_table
2026_02_12_145838_add_profile_fields_to_users_table
2026_02_13_000001_add_soft_deletes_to_users_table
2026_02_14_160000_create_workspaces_table
2026_02_14_160100_add_active_workspace_id_to_users_table
2026_02_14_160200_create_students_table
2026_02_14_160300_create_programs_table
2026_02_14_160400_create_program_items_table
2026_02_14_160500_create_appointments_table
2026_02_15_010000_add_system_role_to_users_table
2026_02_15_010100_create_rbac_tables
2026_02_16_000000_create_audit_logs_table
2026_02_16_000100_create_idempotency_keys_table
2026_02_16_020000_add_whatsapp_tracking_to_appointments_table
2026_02_16_030000_create_appointment_series_table
2026_02_16_030100_create_appointment_reminders_table
2026_02_16_030200_add_series_fields_to_appointments_table
2026_02_16_030300_add_reminder_policy_to_workspaces_table
2026_02_16_040000_create_program_templates_table
2026_02_16_040100_create_program_template_items_table
2026_02_16_050000_add_retry_and_escalation_fields_to_appointment_reminders_table
```

---

## 9. Core Domain Modules

### 9.1 Students (Student Management)

**Endpoints:**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/students` | Student list (paginated) |
| POST | `/students` | Create new student |
| GET | `/students/{id}` | Student detail |
| PUT | `/students/{id}` | Update student |
| PATCH | `/students/{id}/status` | Change status (active/passive) |
| GET | `/students/{id}/timeline` | Student timeline |

**Status values:** `active`, `passive`

**Business logic:**
- A student belongs to a workspace and a trainer
- `owner_admin` can see all students, `trainer` can only see those assigned to them
- The timeline service (program, appointment events) presents the student's history

### 9.2 Programs (Training Programs)

**Endpoints:**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/students/{id}/programs` | Student's programs |
| POST | `/students/{id}/programs` | Create new program |
| GET | `/programs/{id}` | Program detail + items |
| PUT | `/programs/{id}` | Update program |
| PATCH | `/programs/{id}/status` | Change status |
| POST | `/students/{id}/programs/from-template` | Create program from template |
| POST | `/students/{id}/programs/copy-week` | Copy week |

**Status transitions:** `draft` → `active` → `archived`

**Business logic:**
- Each student can have only **one active program** for the same week
- Program templates (ProgramTemplate) are reusable exercise lists
- `copy-week` copies items from an existing program to a new week

### 9.3 Appointments

**Endpoints:**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/appointments` | Appointment list |
| POST | `/appointments` | New appointment (idempotent) |
| GET | `/appointments/{id}` | Appointment detail |
| PUT | `/appointments/{id}` | Update appointment |
| PATCH | `/appointments/{id}/status` | Change status |
| PATCH | `/appointments/{id}/whatsapp-status` | Update WhatsApp status |
| GET | `/appointments/{id}/whatsapp-link` | WhatsApp sharing link |

**Status transitions:**

```
         ┌──────────┐
         │ planned  │
         └────┬─────┘
              │
    ┌─────────┼─────────┐
    ▼         ▼         ▼
┌──────┐ ┌────────┐ ┌───────────┐
│ done │ │no_show │ │ cancelled │
└──┬───┘ └───┬────┘ └─────┬─────┘
   │         │            │
   │    ┌────┘            │
   ▼    ▼                 │
 planned ◄────────────────┘
```

**Conflict detection:** `AppointmentService::create()` checks for overlapping time slots for the same trainer or student. An `AppointmentConflictException` is thrown when a conflict is detected.

### 9.4 Appointment Series (Recurring Appointments)

**Endpoints:**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/appointments/series` | Series list |
| POST | `/appointments/series` | New series (idempotent) |
| GET | `/appointments/series/{id}` | Series detail |
| PUT | `/appointments/series/{id}` | Update series |
| PATCH | `/appointments/series/{id}/status` | Change status |

**Recurrence rule (recurrence_rule):**

```json
{
  "freq": "weekly",
  "interval": 1,
  "count": 12,
  "byweekday": [1, 3, 5]
}
```

**Edit scopes (edit_scope):**
- `single` — Edit only this occurrence
- `future` — Edit this and all future occurrences
- `all` — Edit the entire series

**Series statuses:** `active` → `paused` → `ended`

### 9.5 Reminders

**Endpoints:**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/reminders` | Reminder list |
| GET | `/reminders/export.csv` | CSV export |
| POST | `/reminders/bulk` | Bulk operation |
| PATCH | `/reminders/{id}/open` | Mark as opened |
| PATCH | `/reminders/{id}/mark-sent` | Mark as sent |
| PATCH | `/reminders/{id}/requeue` | Requeue |
| PATCH | `/reminders/{id}/cancel` | Cancel |

For reminder lifecycle details, see [Section 10.4](#104-reminder-lifecycle).

---

## 10. Business Rules

### 10.1 Appointment Status Transition Matrix

| Current Status | Allowed Transitions |
|---------------|---------------------|
| `planned` | `done`, `no_show`, `cancelled` |
| `done` | `planned` |
| `no_show` | `planned`, `done`, `cancelled` |
| `cancelled` | `planned` |

**Rule:** A future appointment cannot be marked as `done`.

### 10.2 Appointment Conflict Detection

`AppointmentService` performs conflict checking on every create and update operation:

```
Existing appointment:  |---10:00---11:00---|
New appointment:           |---10:30---11:30---|  ← CONFLICT!
```

Checked conditions:
- Another appointment for the same **trainer** in the same time slot
- Another appointment for the same **student** in the same time slot
- Cancelled appointments are **not** included in conflict checks

### 10.3 Idempotent Appointment Creation

POST requests are made idempotent via the `EnforceIdempotencyForAppointments` middleware:

```
Client                                   Server
   │                                        │
   │  POST /appointments                    │
   │  Idempotency-Key: "abc-123"            │
   │  Body: { student_id: 5, ... }          │
   │ ──────────────────────────────────────> │
   │                                        │
   │  201 Created + Appointment data        │
   │ <────────────────────────────────────── │
   │                                        │
   │  POST /appointments (RETRY)            │
   │  Idempotency-Key: "abc-123"            │
   │  Body: { student_id: 5, ... }          │
   │ ──────────────────────────────────────> │
   │                                        │
   │  201 Created (FROM CACHE)              │
   │ <────────────────────────────────────── │
```

**Rules:**
- Key format: `[A-Za-z0-9._:-]+`, max 128 characters
- Same key + different body → 422 `idempotency_payload_mismatch`
- TTL: 24 hours (configurable via `config/idempotency.php`)
- Only 2xx responses are cached

### 10.4 Reminder Lifecycle

```
                    ┌─────────┐
                    │ pending │
                    └────┬────┘
                         │
               ┌─────────┼──────────┐
               ▼         ▼          ▼
          ┌────────┐ ┌────────┐ ┌───────────┐
          │ ready  │ │ missed │ │ cancelled │
          └───┬────┘ └───┬────┘ └───────────┘
              │          │
         ┌────┤     ┌────┤
         ▼    ▼     ▼    ▼
    ┌──────┐ ┌────────┐ ┌───────────┐
    │ sent │ │ failed │ │ escalated │
    └──────┘ └───┬────┘ └─────┬─────┘
                 │            │
                 ▼            ▼
              pending      pending
             (retry)      (requeue)
```

**Status transition matrix:**

| Current Status | Allowed Transitions |
|---------------|---------------------|
| `pending` | `ready`, `cancelled`, `missed`, `failed` |
| `ready` | `sent`, `cancelled`, `missed`, `failed` |
| `missed` | `pending` (retry), `cancelled`, `escalated` |
| `failed` | `pending` (retry), `cancelled`, `escalated` |
| `escalated` | `pending` (requeue), `cancelled` |
| `sent` | — (terminal status) |
| `cancelled` | — (terminal status) |

**Default offsets:** **1440 minutes (1 day)** and **120 minutes (2 hours)** before the appointment

**Retry policy:**
- Max attempts: 2
- Backoff: [15, 30] minutes
- On exhaustion: escalation

**Quiet hours:**
- Works with timezone awareness
- Default: 22:00 – 08:00
- Weekend mute option (`weekend_mute`)

---

## 11. Middleware Pipeline

### 11.1 Global API Middleware (Every Request)

Execution order is defined in `bootstrap/app.php`:

| Order | Middleware | Description |
|-------|-----------|-------------|
| 1 | `RequestIdMiddleware` | Validates or generates `X-Request-Id` header |
| 2 | `ForceJsonResponse` | Forces `Accept: application/json` |
| 3 | `ThrottleRequests` | Rate limiting (Laravel built-in) |
| 4 | `SecurityHeadersMiddleware` | Adds security headers |
| 5 | `SetLocaleMiddleware` | `Accept-Language` → app locale |

### 11.2 Route Middleware (Optional)

| Alias | Middleware | Usage |
|-------|-----------|-------|
| `auth:sanctum` | Sanctum Guard | Bearer token validation |
| `user.active` | `EnsureUserIsActive` | Blocks disabled accounts |
| `workspace.context` | `EnsureWorkspaceContext` | Loads workspace + role |
| `idempotent.appointments` | `EnforceIdempotencyForAppointments` | POST idempotency |
| `api.log` | `ApiLogMiddleware` | Request/response logging |

### 11.3 Security Headers

`SecurityHeadersMiddleware` adds the following headers:

```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=()
Cross-Origin-Resource-Policy: same-origin
Cross-Origin-Opener-Policy: same-origin
Content-Security-Policy: default-src 'self'
Strict-Transport-Security: max-age=31536000; includeSubDomains  (prod only)
```

---

## 12. API Response Contract

All responses are returned by `BaseController` in a standard **envelope** format.

### 12.1 Success Response

```php
// app/Http/Controllers/Api/BaseController.php
protected function sendResponse(
    mixed $data = [],
    string $message = 'Success',
    int $code = 200
): JsonResponse
```

```json
{
  "success": true,
  "message": "Student created successfully.",
  "data": {
    "id": 42,
    "full_name": "Ahmet Yılmaz",
    "status": "active"
  },
  "request_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Paginated response:**

```json
{
  "success": true,
  "message": "Success",
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 48,
    "last_page": 4
  },
  "links": {
    "first": "/api/v1/students?page=1",
    "last": "/api/v1/students?page=4",
    "next": "/api/v1/students?page=2",
    "prev": null
  },
  "request_id": "..."
}
```

### 12.2 Error Response

```php
protected function sendError(
    string $message = 'Error',
    array $errors = [],
    int $code = 400
): JsonResponse
```

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "email": ["The email field is required."],
    "phone": ["The phone must be at least 8 characters."]
  },
  "request_id": "..."
}
```

### 12.3 HTTP Status Codes

| Code | Usage |
|------|-------|
| 200 | Successful read/update |
| 201 | Successful creation |
| 401 | Authentication error |
| 403 | Authorization error |
| 404 | Resource not found |
| 405 | Method not allowed |
| 422 | Validation error |
| 429 | Rate limit exceeded |
| 500 | Server error |

### 12.4 Exception Handler

Custom JSON responses for each exception type are defined in `bootstrap/app.php`. All errors include `request_id` and are logged to the `apilog` channel.

---

## 13. Audit Trail

### 13.1 AuditLog Model

```php
// app/Models/AuditLog.php
class AuditLog extends Model
{
    protected $fillable = [
        'workspace_id',      // Which workspace
        'actor_user_id',     // Who did it
        'event',             // What happened (student.created, appointment.updated, ...)
        'auditable_type',    // Polymorphic model class
        'auditable_id',      // Polymorphic model ID
        'changes',           // { before: {...}, after: {...} }
        'request_id',        // Request tracking ID
        'ip_address',        // Client IP address
    ];

    // Relationships: actor (User), workspace, auditable (MorphTo)
}
```

### 13.2 DomainAuditService

```php
// app/Services/DomainAuditService.php
class DomainAuditService
{
    public function record(
        Request $request,
        string $event,           // 'student.created'
        Model $auditable,        // Student model
        array $before = [],      // Previous state
        array $after = [],       // New state
        array $allowedFields = [], // Fields to log
    ): void {
        // If allowedFields is not empty, only save those fields
        // Creates AuditLog record
    }
}
```

**Logged event examples:**
- `student.created`, `student.updated`, `student.status_changed`
- `appointment.created`, `appointment.status_changed`
- `program.created`, `program.status_changed`

### 13.3 Audit Record Example

```json
{
  "id": 157,
  "workspace_id": 1,
  "actor_user_id": 3,
  "event": "student.updated",
  "auditable_type": "App\\Models\\Student",
  "auditable_id": 42,
  "changes": {
    "before": { "phone": "555-0100" },
    "after": { "phone": "555-0200" }
  },
  "request_id": "req-abc-123",
  "ip_address": "192.168.1.100"
}
```

---

## 14. Localization (i18n)

### 14.1 Supported Languages

| Code | Language | File |
|------|----------|------|
| `en` | English | `lang/en/api.php` |
| `tr` | Turkish | `lang/tr/api.php` |

### 14.2 Locale Resolution Process

```
Accept-Language: tr-TR,tr;q=0.9,en;q=0.8
                  │
                  ▼
        Take first 2 characters → "tr"
                  │
                  ▼
        Supported? → Yes → app()->setLocale('tr')
                  │
                  └── No → app()->setLocale('en')
```

`SetLocaleMiddleware` performs this on every request. The exception handler also runs the same logic internally.

### 14.3 Message Categories

```php
// lang/en/api.php (structure)
return [
    'success'            => 'Operation successful.',
    'error'              => 'An error occurred.',
    'too_many_requests'  => 'Too many requests.',
    'forbidden'          => 'Forbidden.',
    'not_found'          => 'Resource not found.',
    'unauthenticated'    => 'Unauthenticated.',
    'validation_failed'  => 'Validation failed.',
    'server_error'       => 'Internal server error.',

    'auth' => [
        'registered'     => 'Registration successful.',
        'login_success'  => 'Login successful.',
        'login_failed'   => 'Invalid credentials.',
        // ...
    ],
    'student' => [
        'created' => 'Student created successfully.',
        'updated' => 'Student updated successfully.',
        // ...
    ],
    'appointment' => [
        'created'   => 'Appointment created successfully.',
        'conflict'  => 'Time slot conflicts with an existing appointment.',
        // ...
    ],
    // ... program, reminder, workspace, trainer, profile, health
];
```

---

## 15. Test Strategy

### 15.1 Test Directory Structure

```
tests/
├── Feature/
│   ├── Api/V1/
│   │   ├── Appointment/
│   │   │   ├── AppointmentFlowTest.php
│   │   │   ├── AppointmentStatusTransitionTest.php
│   │   │   ├── AppointmentConflictTest.php
│   │   │   ├── AppointmentIdempotencyTest.php
│   │   │   └── AppointmentSeriesReminderTest.php
│   │   ├── Program/
│   │   │   └── ProgramFlowTest.php
│   │   ├── Audit/
│   │   │   └── AuditLogWriteTest.php
│   │   ├── Dashboard/
│   │   │   └── DashboardSummaryTest.php
│   │   ├── Profile/
│   │   │   ├── ProfileTest.php
│   │   │   ├── AvatarTest.php
│   │   │   └── DeleteAccountTest.php
│   │   ├── Trainer/
│   │   │   └── TrainerManagementTest.php
│   │   ├── Whatsapp/
│   │   │   └── WhatsAppLinkTest.php
│   │   ├── Workspace/
│   │   │   └── WorkspaceFlowTest.php
│   │   └── HealthTest.php
│   └── Middleware/
│       └── ...
└── Unit/
    └── ...
```

### 15.2 Running Tests

```bash
# Run all tests
php artisan test

# Specific test file
php artisan test --filter=AppointmentFlowTest

# Specific test method
php artisan test --filter=AppointmentFlowTest::test_can_create_appointment

# Run in parallel
php artisan test --parallel
```

### 15.3 Test Factory Usage

```php
// Typical test setup
$user = User::factory()->create(['system_role' => 'workspace_user']);
$workspace = Workspace::factory()->create(['owner_user_id' => $user->id]);
$workspace->users()->attach($user->id, [
    'role' => 'owner_admin',
    'is_active' => true,
]);
$user->update(['active_workspace_id' => $workspace->id]);

$student = Student::factory()->create([
    'workspace_id' => $workspace->id,
    'trainer_user_id' => $user->id,
]);
```

### 15.4 Test Conventions

- **Feature tests** test HTTP endpoints end-to-end
- **Unit tests** test isolated service/model logic
- Every test uses the `RefreshDatabase` trait
- `RbacSeeder` is run in every test to prepare role/permission data
- Factories are defined for each model under `database/factories/`

---

## 16. Adding a New Feature Guide

Steps to follow when adding a new domain module (e.g., "Payments"):

### Step 1: Create Migration

```bash
php artisan make:migration create_payments_table
```

```php
Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
    $table->foreignId('student_id')->constrained()->cascadeOnDelete();
    $table->decimal('amount', 10, 2);
    $table->string('status', 24)->default('pending');
    $table->timestamps();
});
```

### Step 2: Create Model

```bash
php artisan make:model Payment
```

```php
// app/Models/Payment.php
class Payment extends Model
{
    use HasFactory;

    protected $fillable = ['workspace_id', 'student_id', 'amount', 'status'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
```

### Step 3: Create Service

```php
// app/Services/PaymentService.php
class PaymentService
{
    public function __construct(
        private readonly DomainAuditService $auditService,
    ) {}

    public function create(int $workspaceId, int $studentId, array $data): Payment
    {
        return Payment::create([
            'workspace_id' => $workspaceId,
            'student_id' => $studentId,
            ...$data,
        ]);
    }
}
```

### Step 4: Create Policy

```php
// app/Policies/PaymentPolicy.php — Use StudentPolicy pattern
class PaymentPolicy
{
    public function __construct(
        private readonly WorkspaceContextService $workspaceContextService
    ) {}

    public function view(User $user, Payment $payment): bool
    {
        return $this->canAccess($user, $payment);
    }

    private function canAccess(User $user, Payment $payment): bool
    {
        if ((int) $user->active_workspace_id !== (int) $payment->workspace_id) {
            return false;
        }

        $role = $this->workspaceContextService->getRole($user, $payment->workspace_id);
        if ($role === 'owner_admin') return true;

        return (int) $payment->student->trainer_user_id === (int) $user->id;
    }
}
```

### Step 5: Create FormRequest

```bash
php artisan make:request Api/V1/Payment/StorePaymentRequest
```

### Step 6: Create Resource

```bash
php artisan make:resource PaymentResource
```

### Step 7: Create Controller

```php
// app/Http/Controllers/Api/V1/PaymentController.php
class PaymentController extends BaseController
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $payment = $this->paymentService->create(
            $workspaceId,
            $request->validated('student_id'),
            $request->validated(),
        );

        return $this->sendResponse(
            new PaymentResource($payment),
            __('api.payment.created'),
            201,
        );
    }
}
```

### Step 8: Define Route

```php
// routes/api/v1.php — Inside workspace.context middleware group
Route::prefix('payments')->name('v1.payments.')->controller(PaymentController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::get('/{payment}', 'show')->name('show');
});
```

### Step 9: Add i18n Messages

```php
// lang/en/api.php
'payment' => [
    'created' => 'Payment recorded successfully.',
],

// lang/tr/api.php
'payment' => [
    'created' => 'Ödeme başarıyla kaydedildi.',
],
```

### Step 10: Write Tests

```bash
php artisan make:test Feature/Api/V1/Payment/PaymentFlowTest
```

```php
class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_owner_can_create_payment(): void
    {
        // Create user, workspace, student via factories
        // POST /api/v1/payments
        // Assert response: 201, success: true
    }
}
```

### Checklist

- [ ] Migration created and executed
- [ ] Model and relationships defined
- [ ] Factory created
- [ ] Service contains business logic
- [ ] Policy enforces authorization rules
- [ ] FormRequest handles input validation
- [ ] Resource provides JSON transformation
- [ ] Controller defines endpoints
- [ ] Route is under `workspace.context` middleware group
- [ ] EN/TR language messages added
- [ ] Feature tests written and passing
- [ ] `php artisan test` all tests passing
- [ ] `./vendor/bin/pint` code style compliant

---

## All API Endpoints (Reference)

```
GET    /health                                    # Health check
POST   /register                                  # Registration
POST   /login                                     # Login
POST   /forgot-password                           # Password reset request
POST   /reset-password                            # Password reset

# ── Auth required ─────────────────────────────
POST   /logout                                    # Logout
POST   /logout-all                                # Logout from all devices
POST   /refresh-token                             # Token refresh
POST   /email/verify/{id}/{hash}                  # Email verification
POST   /email/resend                              # Resend verification
GET    /users                                     # User list
GET    /me                                        # Profile
PUT    /me                                        # Update profile
DELETE /me                                        # Delete account
POST   /me/avatar                                 # Upload avatar
DELETE /me/avatar                                 # Delete avatar
PUT    /me/password                               # Change password
GET    /me/workspaces                             # Workspace list
POST   /workspaces                                # Create workspace
POST   /workspaces/{id}/switch                    # Switch workspace

# ── Workspace context required ────────────────
GET    /students                                  # Student list
POST   /students                                  # Create student
GET    /students/{id}                             # Student detail
PUT    /students/{id}                             # Update student
PATCH  /students/{id}/status                      # Student status
GET    /students/{id}/timeline                    # Student timeline
GET    /students/{id}/programs                    # Program list
POST   /students/{id}/programs                    # Create program
POST   /students/{id}/programs/from-template      # Program from template
POST   /students/{id}/programs/copy-week          # Copy week
GET    /programs/{id}                             # Program detail
PUT    /programs/{id}                             # Update program
PATCH  /programs/{id}/status                      # Program status
GET    /program-templates                         # Template list
POST   /program-templates                         # Create template
GET    /program-templates/{id}                    # Template detail
PUT    /program-templates/{id}                    # Update template
DELETE /program-templates/{id}                    # Delete template
GET    /appointments                              # Appointment list
POST   /appointments                              # Create appointment (idempotent)
GET    /appointments/{id}                         # Appointment detail
PUT    /appointments/{id}                         # Update appointment
PATCH  /appointments/{id}/status                  # Appointment status
PATCH  /appointments/{id}/whatsapp-status         # WhatsApp status
GET    /appointments/{id}/whatsapp-link           # WhatsApp link
GET    /appointments/series                       # Series list
POST   /appointments/series                       # Create series (idempotent)
GET    /appointments/series/{id}                  # Series detail
PUT    /appointments/series/{id}                  # Update series
PATCH  /appointments/series/{id}/status           # Series status
GET    /reminders                                 # Reminder list
GET    /reminders/export.csv                      # CSV export
POST   /reminders/bulk                            # Bulk operation
PATCH  /reminders/{id}/open                       # Opened
PATCH  /reminders/{id}/mark-sent                  # Sent
PATCH  /reminders/{id}/requeue                    # Requeue
PATCH  /reminders/{id}/cancel                     # Cancel
GET    /dashboard/summary                         # Dashboard summary
GET    /trainers/overview                         # Trainer overview
POST   /trainers                                  # Create trainer
GET    /reports/appointments                      # Appointment report
GET    /reports/students                          # Student report
GET    /reports/programs                          # Program report
GET    /reports/reminders                         # Reminder report
GET    /calendar                                  # Calendar
GET    /calendar/availability                     # Availability calendar
```

> **Note:** All endpoints are under the `/api/v1` prefix.
