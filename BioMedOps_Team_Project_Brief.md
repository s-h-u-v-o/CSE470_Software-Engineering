# BioMedOps v4: Team Project Brief

**Project type:** CSE470 Software Engineering project
**Working title:** BioMedOps: Equipment Service-Readiness and Maintenance-Accountability System
**Target setting:** One facility (hospital imaging/diagnostic department or private diagnostic centre), five seeded equipment categories
**Primary stack:** Laravel + MySQL + Blade, Alpine.js only where useful
**Role in portfolio:** Backup proposal to LotLine (LotLine remains the primary candidate)
**Project boundary:** Academic prototype. Not a clinical-safety, regulatory or accreditation system.

---

## 1. Executive summary

BioMedOps tracks diagnostic equipment from arrival through commissioning, service, repair and retirement. It answers three questions reliably:

1. **Technical readiness:** Is this machine technically fit to use now, and if not, which record blocks it?
2. **Staffing readiness:** Is a qualified operator authorized and rostered for it now?
3. **Maintenance accountability:** Which repairs and preventive tasks are late, who owns them, how long was the machine down, and which commitments were missed?

Core ideas:

- Three separate state models: asset lifecycle, ticket workflow, derived readiness. Readiness is never a free-editable flag.
- Technical readiness, staffing readiness and service availability are reported separately.
- Downtime derived from append-only status events, merged into intervals. Never double-counted.
- SLA clock in integer minutes with authorized, audited pauses.
- Explicit gap detection with named codes.
- Tamper-evident audit trail, limits documented.

The academic contribution is an independently implemented, well-tested system. It is not a claim that equipment-management software is new in Bangladesh or elsewhere.

**Core design principle:** every status shown must be derivable from stored records. If records contradict each other, the system reports the gap. It never silently shows a clean status.

## 2. Problem and evidence

### What the evidence supports

- **PPRC study of 22 public health facilities:** A report published by *The Daily Star* on 30 July 2026 describes a study covering 22 public facilities across Bangladesh's eight divisions. It reports that 52% of diagnostic equipment had not been used during the preceding year. The report attributes service disruptions to non-functional equipment, shortages of accessories/consumables, and lack of trained technologists. The sample is small and must not be presented as a national census.
  - https://online.thedailystar.net/health/news/medicines-running-out-labs-falling-apart-4235946
  - Related Prothom Alo coverage: https://en.prothomalo.com/bangladesh/904ggpi81h
- **Historical equipment inventory:** A *Daily Star* editorial updated on 5 November 2023 cited 3,331 idle equipment items across 84 public hospitals, including about 933 reportedly beyond repair. These are historical reported figures, not a current count.
  - https://www.thedailystar.net/opinion/editorial/news/when-will-public-hospitals-be-accountable-3462161
- **Delayed repair example:** *The Daily Star* reported on 3 June 2021 that a mammography unit at Chattogram Medical College Hospital had been out of service for about seven months; the hospital cited slow distributor response.
  - https://www.thedailystar.net/city/news/7-months-fix-key-medical-equipment-2104613
- **Manpower shortage example:** A June 2026 report described equipment at Sirajganj Medical College Hospital as idle and deteriorating amid staffing shortages.
  - https://observerbd.com/news/580092
- **Redistribution policy signal:** *The Business Standard* reported on 31 July 2026 that the government had ordered an inventory of unused public-hospital equipment and redistribution to facilities where it could be used, with progress reports every 15 days.
  - https://www.tbsnews.net/bangladesh/health/govt-orders-redistribution-idle-medical-equipment-public-hospitals-1502681

### Careful wording for the SRS

The evidence supports concerns about equipment utilisation, maintenance, consumables and staffing in public facilities. It does not prove that every facility shares the same problem or uses the same workflow. Software can improve visibility, assignment and accountability; it cannot supply technologists, spare parts or physical repairs.

### Evidence limitations and current status

- Current day-to-day use of eAMS across all facilities is not independently established by the sources below.
- A separate equipment-monitoring initiative, MEMIMS, was reported as planned for 300 devices in 114 government hospitals by December 2025. I have not verified whether it subsequently launched; do not describe the planned system as currently operational.
- News reports and the 22-facility study are supporting context, not a representative survey of every Bangladeshi hospital.

### Public/private setting distinction

Most of the cited evidence concerns public hospitals. Interview access may be easier at a private diagnostic centre. An interview there validates that centre's workflow only; it cannot be generalized to public hospitals without further evidence.

## 3. Related work and positioning

### Bangladesh eAMS

A 2020 Management Sciences for Health (MSH)/USAID MTaPS article describes Bangladesh's web-based Electronic Asset Management System (eAMS), integrated with the Ministry of Health and Family Welfare's Supply Chain Management Portal. It describes functional-status tracking, repair action, decommissioning and reallocation, with assets linked to the national Table of Equipment. The article states that eAMS had initially been rolled out to 12 district hospitals and that nationwide rollout was planned by June 2020; a plan is not proof that the target was completed.

- MSH/MTaPS: https://msh.org/story/new-tool-to-boost-efficient-equipment-use-in-bangladesh-hospitals/
- The official Supply Chain Management Portal describes eAMS as a module covering asset registration through decommissioning, intended to support location tracking, maintenance and procurement planning: https://scmpbd.org/asset-tracking.php
- A DGHS eAMS page states that implementation reached all 61 district hospitals: https://hospitaldghs.gov.bd/eams-electronic-asset-management-system/
- A separate DGHS programme page reports implementation at 64 district-level hospitals during the 2017–2024 programme period: https://hospitaldghs.gov.bd/innovation-service-management/

The 61 and 64 figures use different page contexts and are not reconciled here. They are historical official claims, not proof of active current usage at every facility. Do not claim that BioMedOps solves a problem eAMS lacks unless the actual workflow has been inspected and verified.

### Planned MEMIMS initiative

*The Daily Star* reported on 27 August 2025 that the health ministry had moved to introduce a Medical Equipment Maintenance Information and Monitoring System (MEMIMS), with a first phase planned for 300 devices in 114 government hospitals and a December 2025 target. The article said administrative/financial approval and tender steps were still pending. Current launch/operational status has not been verified, so describe this as a reported plan, not a live system.

- https://www.thedailystar.net/health/healthcare/news/new-initiative-govt-hospitals-digital-monitoring-system-medical-equipment

### Commercial CMMS products

Atto Asset Manager, Arcmedix and Zapium advertise equipment registers, work orders, preventive maintenance and related healthcare-maintenance functions. These are vendor descriptions, not independent assessments of software quality or market adoption.

- https://about.attocmms.com/product/asset-manager/
- https://arcmedix.com/industries/healthcare-facilities
- https://www.zapium.com/software/cmms/healthcare/maintenance/

### Positioning statement for the SRS

> BioMedOps is a single-facility equipment service-readiness and maintenance-accountability prototype. It independently implements and tests technical readiness, operator competency, SLA timing, downtime accounting and explicit gap detection, with reproducible tests for state transitions and reporting accuracy.

The project does not claim that asset management or hospital maintenance software is new, that existing systems lack these capabilities, or that the prototype is clinically certified. Do not copy code, screens or text from existing tools. Cite eAMS, MEMIMS and commercial CMMS products as related work.

## 4. Scope boundaries

### In scope

- One facility, five equipment categories (for example X-ray, ultrasound, ECG, ventilator, autoclave).
- Asset registry, receiving, warranty/AMC terms, commissioning.
- Operator authorization and shift roster.
- Derived technical readiness, staffing readiness, service availability.
- Maintenance tickets, severity, blocking rules, SLA clock.
- Event-derived downtime and availability reporting.
- Preventive-maintenance plans and due dates.
- Gap detection, audit trail, seeded test data.

### Out of scope

- Clinical-safety certification, regulatory or accreditation claims.
- Multi-facility reallocation, procurement, budgeting, depreciation accounting.
- Real vendor, ERP or government APIs, including eAMS integration.
- IoT sensors, barcode/RFID hardware.
- Patient data of any kind. All data synthetic.
- Business-hours calendars for SLA.
- AI-based fault prediction.
- Automatic replacement decisions.

## 5. User roles

1. **Store keeper:** records receiving, installation steps, custody.
2. **Department user:** reports faults, sees readiness of their equipment.
3. **Biomedical engineer:** triages, repairs, records PM, closes work.
4. **Supervisor:** approves deferrals, verifies repairs, authorizes SLA pauses, reclassifies severity.
5. **Administrator:** manages users, roles, configuration, operator authorizations.
6. **Auditor (read-only):** inspects history, reports, audit chain.

Authorization enforced on the server, not only in the interface.

## 6. Core state model

### A. Asset lifecycle (stored, guarded transitions)

`received_crated` → `installed` → `commissioned` → `retired`

- `installed` requires an installation record.
- `commissioned` requires a completed checklist, including relevant site-readiness and category-specific checks.
- `retired` requires no open tickets and an authorized decision with a reason.
- Posted lifecycle history is not silently edited; corrections require explicit, audited correction records.
- Invalid transitions are rejected by service-layer rules, not merely hidden in the UI.

### B. Ticket workflow (stored, guarded transitions)

`reported` → `triaged` → `assigned` → (`awaiting_parts` / `awaiting_vendor`) → `repaired` → `verified` → `closed`

- Failed verification returns the ticket to `assigned`; the history remains.
- `closed` is immutable. A new fault creates a new ticket.
- Severity is set at triage: `critical`, `major`, `minor`.
  - `critical`: blocks technical readiness immediately, including while the ticket is still `reported` or `triaged`.
  - `major`: blocks readiness when the facility's configured rule says it does.
  - `minor`: does not block readiness by default.
- Only an authorized supervisor can reclassify severity. The old value, new value, actor, reason and timestamp are recorded.
- A ticket can be non-blocking; the mere presence of any open ticket must not automatically make every asset unavailable.

### C. Derived technical readiness (never a free-editable flag)

**Technical readiness at time `t`** requires all of the following:

- lifecycle = `commissioned`;
- no active quarantine;
- no active authorized out-of-service hold;
- no open ticket whose severity/configuration blocks use;
- required calibration/safety checks are valid where the category requires them.

An out-of-service decision is an explicit record/event with an authorized actor, reason, start time and resolution/expiry rule. It is not a hidden boolean that can be casually edited.

**Derived display status precedence:**

`quarantined` > `blocked_by_ticket` > `out_of_service` > `not_ready` > `technically_ready`

Display the specific blocker separately. An active blocking ticket may be labelled “under repair” when repair work has started, but a critical ticket blocks readiness even before assignment or repair. `technically_ready` means technical conditions pass; it does not mean service is staffed.

Gap detection must not rely on a cached readiness flag: readiness is derived from the source records above. G8 instead reports contradictions in those records, including a retired asset with an open ticket or active hold, a work-state ticket without an assignee, a closed ticket with an unresolved SLA pause, or downtime events that the ledger cannot derive.

### D. Staffing readiness and service availability

**Staffing readiness at time `t`:** at least one operator is authorized for the asset category, the authorization is valid at `t`, and a roster shift covers `t`.

**Service availability at time `t`:**

`service_available = technical_ready AND staffing_ready`

If technical readiness passes but staffing does not, show **“No qualified operator rostered”** as a staffing blocker. Do not label the equipment broken. Staffing can never override a technical block.

Historical reports must use time-effective, versioned authorization and roster records so an edit today does not silently rewrite a past report.

## 7. Suggested core entities

- `Asset`, `AssetCategory` (required checks, operating schedule)
- `Receiving`, `WarrantyTerm`
- `CommissioningChecklist`, `ChecklistItemResult`
- `Operator`, `OperatorAuthorization` (category, valid_from, valid_to), `RosterShift`
- `Ticket`, `TicketEvent`, `SlaPolicy`, `SlaPause`
- `StatusEvent` (append-only)
- `PmPlan`, `PmPlanVersion`, `PmTask`, `PmDeferral`
- `CalibrationRecord`, `Certificate` (path + sha256)
- `PartsRequisition`, `Vendor`, `VendorServiceLog`
- `CorrectiveAction`
- `GapFinding`
- `AuditEntry`, audit-chain head record

## 8. Time and recording policy

- Store timestamps in UTC. Durations as integer minutes. No floating point in SLA or availability.
- Keep `occurred_at` and `recorded_at` on events.
- Sprint 1: server time only, no backdating.
- Later sprint: correction workflow. Posted events are never edited. Corrections are compensating events with reason, actor, approver.
- A backdated entry that contradicts existing events is rejected, or routed through the correction workflow.

## 9. SLA engine

- Per policy and severity: response target (reported to assigned) and resolution target (reported to verified), in integer minutes.
- Elapsed = (end − start) in whole minutes, minus eligible pause intervals. Define one rounding rule (floor to whole minutes) and test it.
- Pauses allowed only while ticket is `awaiting_parts` or `awaiting_vendor`, and only if the SLA policy or contract flag permits.
- Every pause records reason code, authorizing role and timestamp. Resume by authorized role, timestamped. Both audited.
- Breach: elapsed exceeds target. Breach with no escalation raises G4.
- No business-hours calendar in this version.

**Required test cases (30 hand-calculated):** exact boundary (equal to target, one minute over), repeated pauses, pause then reopen, breach while paused, pause where policy forbids, zero-length pause.

## 10. Downtime ledger and availability

### Status and readiness events

Use append-only events with `asset_id`, previous/next derived state or blocking cause, `occurred_at`, `recorded_at`, `cause_type` (`planned` or `unplanned` where applicable), nullable `ticket_id`, actor and reason. Staffing coverage is derived from time-bounded operator authorizations and roster shifts; historical changes must be versioned or recorded as corrections.

### Downtime intervals

- Derive equipment downtime from asset/status and blocking-cause events, not by summing ticket durations. Tickets explain the cause; two tickets on one asset cannot double-count the same unavailable minutes.
- Downtime counts only after first commissioning. Pre-service `not_ready` time is not operational downtime.
- Merge overlapping technical downtime intervals. Where planned and unplanned downtime overlap, count the overlapping minutes once and classify them as **unplanned**.
- Preserve separate cause records when a new unplanned fault occurs during an existing planned maintenance interval, so the reporting rule can be applied correctly.

### Availability denominator and formula

Use a configurable scheduled operating calendar per asset or asset category. All intervals are clipped to the selected reporting window and to scheduled operating minutes.

- `S` = scheduled operating minutes in the reporting interval.
- `T` = technical-unavailability intervals intersecting those scheduled minutes.
- `O` = operator/staffing-unavailability intervals intersecting those scheduled minutes.
- `U` = duration of the union of `T` and `O`; overlapping minutes are counted only once.

`service_availability_% = 100 × (1 − U / S)` when `S > 0`.

Report three distinct measures:

1. **Equipment downtime:** technical-unavailability minutes, split into planned and unplanned time using the overlap rule.
2. **Staffing unavailability:** scheduled minutes without the required qualified operator.
3. **Service unavailability / service availability:** the union of technical and staffing unavailability divided by scheduled operating minutes.

If `S = 0`, report “not applicable” rather than divide by zero. Do not add equipment downtime and staffing unavailability directly; calculate their union to avoid double-counting.

**Required interval tests:** overlapping and nested intervals, reporting-window boundaries, planned interval fully inside unplanned interval, adjacent intervals, two tickets on one asset simultaneously, technical downtime overlapping an absent operator, and zero scheduled minutes.

## 11. Preventive maintenance

- PM plan per asset or category, versioned (interval, effective_from). Changing an interval creates a new version. Past due dates are not rewritten.
- Due engine generates tasks from last completed PM plus applicable interval version. Overdue list.
- **Deferral:** request, reason, approver, decision time. Keeps original due date and stores approved revised date. A deferral does not override an overdue blocking calibration or safety condition.
- Completing PM posts a record and (if planned downtime) status events.

## 12. Scheduler (secondary)

- Assign PM tasks to engineers and slots.
- Objective: weighted tardiness `sum_i w_i × max(0, C_i − D_i)`, where `w_i` is criticality weight, `C_i` scheduled completion, `D_i` due time.
- Constraints: engineer skill, duration, availability, allowed maintenance windows, no double-booking.
- Compare: naive earliest-due baseline, greedy priority scheduler, brute-force optimum on instances of 6 tasks or fewer. Report optimality gap.
- Seed instances with real contention (fewer engineer slots than tasks). Otherwise greedy is trivially optimal.
- Local search is a stretch goal only. It must never block the project.

## 13. Gap diagnosis rules

Each rule needs positive and negative test cases and a message naming the relevant records. A gap finding describes a record or workflow inconsistency; it does not prove a physical fault.

| Code | Rule | Dependency / rollout tier |
|---|---|---|
| G1 | Asset remains `received_crated` longer than configured threshold | Core |
| G2 | Warranty/AMC expires while the asset remains uncommissioned or out of service | Requires F04 warranty/AMC tracker; secondary |
| G3 | Ticket remains unassigned beyond the configured threshold | Core |
| G4 | SLA is breached with no required escalation recorded | Requires F19 escalation; secondary |
| G5 | PM overdue with no approved deferral | Core, when PM due engine is active |
| G6 | Required calibration/safety check overdue | Requires F24 detailed calibration/safety records; secondary |
| G7 | Lifecycle says `commissioned` without a passed commissioning checklist | Core |
| G8 | Active source records contradict each other: a retired asset has an open ticket or active hold; a work-state ticket has no assignee; a closed ticket has an unresolved SLA pause; or downtime events cannot be derived by the ledger | Core |
| G9 | Repeated failures (N tickets on an asset within a configured window) have no corrective-action record | Requires F23 corrective-action workflow; secondary |
| G10 | Operator authorization is expired or invalid while the operator is rostered for the asset category | Core |

**Important dependency rule:** F25 is the gap-detection framework, and the initial implementation activates G1, G3, G7 and G8. Add G2, G4, G5, G6, G9 and G10 only after their required modules are implemented and tested. Do not claim all G1–G10 are complete when their dependencies are absent.

For every active code, the result names the exact records involved, the condition that failed and a suggested next review step. A missing record is not automatically evidence of a physical equipment discrepancy.

## 14. Transactions, concurrency and locking

### Ticket claim

1. Begin transaction.
2. Lock the ticket row with `lockForUpdate()`.
3. Recheck state is `triaged` and unassigned.
4. Set assignee, append ticket event, write status event if needed.
5. Append audit event, update audit head.
6. Commit.

### Global lock order

1. asset rows, sorted by id
2. ticket rows, sorted by id
3. PM task rows, sorted by id
4. slot/roster rows, sorted by id
5. related business rows (e.g. requisitions or deferrals), sorted by id when needed
6. audit-chain head last

Every service that locks more than one row must follow this documented order. If a workflow needs a different order, redesign it rather than adding an undocumented exception. Keep transactions short; retry deadlocks only as a fallback.

### Required concurrency tests

- 20 independent processes claim one ticket through the real service. Exactly one succeeds, the rest receive a clear rejection.
- Two processes assign the same engineer to overlapping slots. Exactly one succeeds.
- A single PHPUnit process issuing sequential calls does not count. Use separate processes with separate DB connections (`artisan` commands or Symfony Process).

## 15. Audit trail

- Canonical payload serialization, sequence number, event metadata and previous hash in the hash input.
- Audit event written in the same transaction as the business change.
- Chain head locked on append.
- Certificate hashes (sha256) recorded in the chain when calibration documents are uploaded.
- **Tamper-evident, not tamper-proof.** Whole-database rewrite plus re-hash can replace the chain. State this in the SRS. No external signing service.

## 16. Feature candidates (28)

These are candidates for faculty approval. The professor may merge related items. Only features actually completed to their acceptance criteria and marked “Done” count. Do not assume 22 table rows automatically equal 20 accepted features. Get the counting granularity approved before implementation, then set the completion target against the approved feature list. Each item needs a unique ID and acceptance criteria in the SRS.

### Assets, lifecycle, people: Member A

| ID | Feature | Acceptance criterion |
|---|---|---|
| F01 | Asset registry and lifecycle state machine | Valid transitions succeed with history. Invalid transitions rejected by service. |
| F02 | Receiving record with warranty/AMC terms | Receipt stores serial, category, terms, received date. Duplicate serial rejected. |
| F03 | Installation and commissioning checklist gate | Asset cannot become `commissioned` until mandatory items pass. Failed items listed. |
| F04 | Warranty/AMC tracker and alerts | Expiring terms flagged. Warranty running while uncommissioned flagged (G2). |
| F05 | Operator competency registry and shift roster | Authorizations per category with validity dates. Roster shifts without overlap per operator. |
| F06 | Expected-equipment list gap report (optional) | Compares configured expected list against registry, reports missing and surplus. Real list contents verified from primary source first. |
| F07 | Custody transfer with two-person handover (optional) | Transfer completes only after sender and receiver both confirm. Same person cannot do both. |

### Readiness, downtime, PM: Member B

| ID | Feature | Acceptance criterion |
|---|---|---|
| F08 | Derived technical readiness | Result matches the full truth table for lifecycle, blocking tickets, quarantine/out-of-service holds and required checks. No free-editable readiness flag. Critical tickets block immediately, even before assignment. |
| F09 | Staffing readiness and service availability | Shows "No qualified operator rostered" when technically ready but unstaffed. Service availability is derived from technical readiness AND staffing readiness; staffing cannot override a technical block. |
| F10 | Event-derived downtime ledger | Intervals merge correctly. Overlapping tickets do not double-count. Planned/unplanned overlap counts once as unplanned. |
| F11 | Availability report | Planned, unplanned, unavailable minutes and availability % within scheduled time. Matches ground truth. |
| F12 | PM plans with interval versions and due engine | Due dates computed from last PM and interval version. Past due dates not rewritten. |
| F13 | PM deferral approval workflow | Original due date retained, revised date, reason, approver, time stored. Cannot override blocking calibration. |
| F14 | PM scheduler (weighted tardiness) | Produces conflict-free schedule. Reports objective vs baseline and brute-force on small instances. |
| F15 | PM slot booking and rescheduling with conflict guard | Concurrent bookings of one slot or engineer yield exactly one success. Reschedule revalidates constraints. |

### Tickets and SLA: Member C

| ID | Feature | Acceptance criterion |
|---|---|---|
| F16 | Ticket workflow with severity and blocking rules | Transitions guarded. Severity determines blocking. Reclassification audited. |
| F17 | Transactional ticket claim | 20 concurrent claims, exactly one success. |
| F18 | SLA clock with authorized pauses | 30 hand-calculated cases pass. Pause rejected where forbidden. |
| F19 | Escalation chain | Breach moves ticket to next authority with record. Breach with no escalation raises G4. |
| F20 | Spare-parts requisition linked to ticket | Requisition links to ticket, moves it to `awaiting_parts`, receipt resumes work. |
| F21 | Vendor service log and scorecard | Records vendor visits, response and resolution times. Scorecard computed from records only. No ranking claims from tiny samples. |
| F22 | Repair closeout checklist and supervisor verification | Ticket cannot reach `verified` without checklist and a supervisor who is not the repairer. |
| F23 | Recurring-failure corrective-action workflow | Repeated failures trigger a corrective-action record with owner and outcome. Raises G9 if absent. |

### Compliance and audit: Member D

| ID | Feature | Acceptance criterion |
|---|---|---|
| F24 | Calibration/safety-check records with certificate hash | Record stores readings, result, certificate sha256. Missing or altered file detected. |
| F25 | Gap detection engine (staged G1-G10 rules) | Core G1, G3, G7 and G8 each fire on positive cases and stay silent on negative cases. G8 checks contradictory source records rather than a cached readiness value. G2, G4, G5, G6, G9 and G10 are enabled only after their dependencies are delivered. Messages name the records involved. |
| F26 | Audit trail viewer and chain verification | Authorized users inspect events. Verification reports the first broken link. |
| F27 | Notifications with dedupe (optional) | In-app reminders for due or breached items. Repeats suppressed. |
| F28 | Spreadsheet import wizard (optional) | Column mapping, preview, row-level errors, no duplicate or partial posting. |

### Priority tiers

**Core (13):** F01, F02, F03, F05, F08, F09, F10, F11, F12, F16, F17, F18, F25 (core gap rules only: G1, G3, G7, G8).
**Secondary (11):** F04, F13, F14, F15, F19, F20, F21, F22, F23, F24, F26.
**Optional (4):** F06, F07, F27, F28.

The core gap detector must not claim G2, G4, G6 or G9 until its secondary dependencies exist. “Core” means the first implementation tier, not permission to skip its acceptance tests.

Stretch (not candidates): repair-versus-replace decision-support flag, department impact report. Only if all else is done.

The evaluation harness and seed generator are testing infrastructure, not counted as features.

## 17. Workload division

- **Member A — asset and operator data:** F01–F07. Registry, lifecycle, receiving, commissioning, warranty/AMC, operator competency and roster. Owns lifecycle/commissioning gap rules and contributes seed cases.
- **Member B — readiness, downtime and PM:** F08–F15. Derived readiness, downtime intervals, availability reports, PM plans/due engine, scheduler and booking. Owns availability ground-truth generator/tests.
- **Member C — tickets and SLA:** F16–F22. Ticket workflow, severity rules, transactional claim, SLA/pause logic, escalation, parts requests, vendor service log and repair verification. Owns ticket/SLA gap rules.
- **Member D — cross-cutting integrity:** F23–F28. Corrective action, calibration/safety records, gap-engine orchestration, audit viewer/chain verification, and optional notification/import workflows if time allows.

**Shared ownership:** each module owner implements its own domain rules and positive/negative tests. Member D coordinates the shared GapDetector, but other members implement and test rules that depend on their own module. Member A and D jointly own seeded data/ground truth; all four participate in end-to-end and integration tests. Each member reviews another module and can explain the full MVC flow for the viva.

Git history must reflect real work. Report sprint attainment and incomplete work honestly.

## 18. Architecture and stack

- Laravel (PHP), MySQL, Blade, Alpine.js if needed. PHPUnit and Laravel feature tests.
- No Python backend, no MERN, no external AI API.
- **Models:** assets, tickets, events, plans, operators, audit entries.
- **Controllers:** authenticate, authorize, validate request shape, call services. No domain logic.
- **Views:** Blade, no business logic.
- **Services:** `LifecycleService`, `ReadinessService`, `StaffingService`, `TicketService`, `SlaEngine`, `DowntimeLedger`, `AvailabilityReport`, `PmService`, `PmScheduler`, `GapDetector`, `AuditChainService`.

## 19. Four-week sprint plan

### Sprint 1: foundation and core gate

- SRS draft, faculty feature-count clarification, schema, MVC skeleton and roles.
- Lifecycle guards and the minimum ticket/status-event model needed to test readiness.
- Derived technical readiness, staffing readiness and service-availability services.
- SLA clock with hand-calculated cases.
- Event-derived downtime and availability calculation.
- Seed generator with independently recorded ground truth.
- Thin Blade screens only. Most acceptance tests run through services/PHPUnit.

**Sprint 1 gate — all five conditions required:**

1. Invalid lifecycle transitions are rejected by service rules.
2. Technical readiness, staffing readiness and service availability match a full truth table, including active holds and critical tickets before assignment.
3. Thirty hand-calculated SLA cases pass.
4. Equipment downtime, staffing unavailability and service availability match ground truth, including overlap and scheduled-time boundaries.
5. At least one seeded data-quality fault is detected by an implemented core gap rule (e.g. commissioned asset without a passed checklist).

If a gate fails, cut optional work and fix the core. Do not switch projects mid-course.

### Sprint 2: maintenance accountability

- Ticket claim with database locking, assignment, `awaiting_parts` / `awaiting_vendor` and authorized SLA pauses.
- Breach detection, escalation and pause audit history.
- PM plans, interval versions, due engine and deferral workflow.
- Operator authorization/roster hardening.
- Process-level concurrency tests with separate processes and database connections.

### Sprint 3: operations and dependent rules

- PM scheduler and booking conflict guard, if core remains on track.
- Calibration/safety-check records and certificate hashing.
- Repair closeout, supervisor verification and corrective-action workflow.
- Extend gap detection to G1–G10 only as each dependency becomes available.
- Complete downtime and availability reports.

### Sprint 4: proof and finish

- Audit viewer and chain verification.
- Full evaluation runs, integration tests and environment notes.
- Cut unfinished secondary/optional work as needed; do not mark incomplete items Done.
- Final SRS, class and MVC diagrams, README, approved feature-completion table, sprint history, testing summary and honest AI-use disclosure.

Sprint 4 is for finishing, testing and documenting—not for first building the hardest logic.

## 20. Evaluation plan

| Evaluation | Method | Expected result |
|---|---|---|
| Lifecycle guards | Attempt every invalid transition through services | All rejected with an explanation |
| Readiness truth table | Enumerate lifecycle, ticket severity/state, quarantine/hold, required checks, operator authorization and roster | Derived result matches the predefined expected result |
| SLA engine | 30 hand-calculated cases with pauses, reopen, boundaries and forbidden pauses | All match |
| Downtime/availability | Generated intervals with known overlaps, nesting, schedule boundaries and staffing gaps | Merged and union durations match ground truth; no double-counting |
| Ticket claim race | 20 independent processes claim one ticket | Exactly one succeeds |
| Booking race | Two processes attempt conflicting assignment to one engineer/slot | Exactly one succeeds |
| Gap rules | Positive and negative case for every enabled G-code | Expected findings only, with relevant records named |
| Scheduler | Seeded contention, six tasks or fewer | Compare naive and greedy objective against brute-force optimum; report optimality gap |
| Audit verification | Alter, delete or reorder an entry in a test database | Chain verification reports the first broken link; full-rewrite limitation documented |
| Certificate integrity | Delete a file or alter one byte | Missing/altered state detected |

Report measured results and the test environment. Do not promise production-scale performance. Evaluation infrastructure does not count as a user-facing feature.

## 21. Interview plan and falsification criteria

Interview one biomedical engineer or diagnostic-centre technician. Use synthetic examples only; do not request patient data, confidential service contracts or identifiable records.

Ask the interviewee to walk through one recent equipment failure step by step: how it was reported, who triaged it, who repaired it, what delayed the repair, how return to use was approved, and what records were retained. Ask about staffing, vendor promises, preventive maintenance and downtime only where they actually affect the workflow.

**Decide in advance what to drop:**

- If operator absence has never blocked service in that centre's experience, move staffing readiness out of the core.
- If no repair deadlines or vendor commitments exist, move the SLA engine out of the core.
- If downtime is not tracked and not decision-relevant, shrink F10/F11; do not disguise data entry as a proven pain point.
- If no preventive-maintenance routine exists, keep PM secondary.
- If the interview shows the centre already uses eAMS or a capable CMMS for these exact workflows, ask what remains manual or poorly supported. If no meaningful gap is established, do not invent one.

The interview validates that facility's workflow only. It does not establish national demand. Keep only features supported by the interview and required by the approved course feature list.

## 22. Main risks and responses

| Risk | Response |
|---|---|
| Ticket system is plain CRUD | Depth lives in readiness derivation, SLA, downtime intervals, concurrency, gap engine, audit chain. Protect these. |
| Overlap with eAMS and commercial CMMS | Cite as related work. Claim tested implementation only. |
| Evidence is news and one small study | State limits. Do not generalize. |
| Public-sector evidence, private-centre validation | State both in SRS. Keep features generic. |
| Mixing equipment fault with staffing absence | Separate technical and staffing readiness. Separate metrics. |
| Double-counted downtime | Event-derived intervals, merged, with overlap tests. |
| SLA pause abused | Pause only in allowed states, reason code, authorized role, audited. |
| Faculty merges features | Submit 28 candidates with acceptance criteria, get the accepted feature granularity in writing, and set the Done target against that approved list. Do not assume row count equals accepted feature count. |
| Optional features counted as done | Count only completed features. Mark status honestly. |
| Scope creep into clinical or regulatory claims | Out of scope. No claims. |
| Scheduler blocks project | Keep secondary. Greedy only. |
| Current eAMS use is unknown | Cite documented scope and historical rollout; do not claim current operation at every facility. |
| MEMIMS status unknown | Describe the August 2025 report as a plan; do not call it live without newer confirmation. |
| Public-sector evidence, private-centre validation | State both in SRS; private-centre interview validates only that centre. |

## 23. Decisions requiring approval

- Faculty approval of the 28 candidates and counting granularity.
- Confirmation that Laravel + MySQL + Blade satisfies the MVC and stack rules.
- Agreement on severity and blocking rules, SLA pause policy, overlap classification rule.
- Agreement on the operating-schedule denominator for availability.
- One interview completed and falsification criteria applied.
- Final source/date check for cited articles and clear wording on eAMS historical rollout versus current use.
- MEMIMS status remains unverified; describe its announced plan only unless a newer primary source confirms implementation.
- Confirmation that readiness includes authorised out-of-service holds and that critical tickets block before assignment.
- Agreement on union-based service-availability calculation and the staged gap-rule dependencies.

## 24. Short pitch

> **BioMedOps is a single-facility equipment service-readiness and maintenance-accountability prototype.** It records equipment from arrival to retirement and separately derives technical readiness, qualified-operator coverage and service availability. It includes event-derived downtime and SLA accounting, preventive-maintenance due tracking, explicit gap detection and a tamper-evident audit trail. Correctness is evaluated with truth-table tests, hand-calculated SLA cases, interval ground truth and real concurrent-process tests. Its related-work discussion acknowledges Bangladesh eAMS, the reported MEMIMS initiative and commercial CMMS products. It is an academic prototype, not a clinical or regulatory system.

## 25. Final direction

Take this updated brief and the 28-feature candidate table to the professor alongside LotLine. Get feature granularity and MVC approval before implementation. Update the related-work paragraph with the checked eAMS sources; describe MEMIMS as a reported plan unless its later status is verified. Run the interview and apply the falsification criteria. In Sprint 1, build readiness, SLA and downtime calculations first and pass all five gates. If a gate fails, narrow scope and fix the model. Do not add optional modules until the core agrees with ground truth.
