# Phase 1G — Certificate System & Issuance Architecture Specification

**Project**: Listening Community – Suicide Prevention Campaign (LC-SPC)  
**Document**: Architecture Design Specification — Phase 1G  
**Baseline Commit**: `fc3bf88` (Phase 1F Approved, Locked, and Code-Frozen)  
**Target Repository**: `teamincubation/LC-SPC`  
**Production URL**: `https://teami.in/LC/`  
**Status**: ARCHITECTURAL SPECIFICATION & REVISION 1 (NO IMPLEMENTATION)

---

# REVISION 1 — Architectural Resolutions

This section documents the authoritative resolutions to the critical architectural questions raised during architectural review. These decisions supersede preliminary assumptions and govern all subsequent design sections.

---

### R1.1 Resolution 1: Re-issuance vs. Unique Constraint `UNIQUE (registration_id, type)`

#### The Architectural Challenge
The current baseline table `certificates` contains:
```sql
UNIQUE KEY `uk_cert_reg_type` (`registration_id`, `type`)
```
In MySQL/InnoDB, this composite unique constraint applies indiscriminately across all rows. If a certificate row exists with `registration_id = 12, type = 'participation', status = 'revoked'`, attempting to insert a second row with `registration_id = 12, type = 'participation', status = 'active'` triggers a fatal MySQL error:
`SQLSTATE[23000]: 1062 Duplicate entry '12-participation' for key 'uk_cert_reg_type'`.

#### Evaluation of Architectural Approaches

| Criterion | Approach A: In-Place Update of Same Row | Approach B: Explicit Superseded Credential (New Row) | Approach C: Strict Revocation Finality (No Re-issuance) |
| :--- | :--- | :--- | :--- |
| **Relational Mechanism** | Overwrites existing row with new token & number, or keeps old token and alters name/status. | Leaves old row as `status = 'revoked'`; inserts a new row as `status = 'active'`. | Revocation is permanent. No second certificate of the same type can ever be issued. |
| **Old Physical Paper QR Scan** | Returns **HTTP 404** (token erased) OR returns mismatched name. | Returns **HTTP 200 REVOKED / SUPERSEDED** indicating the paper document is void. | Returns **HTTP 200 REVOKED**; participant permanently forfeits certificate. |
| **Forensic Auditability** | Destroys primary token record in table; relies entirely on off-row audit logs. | Complete multi-row cryptographic timeline in `certificates` + `audit_logs`. | Preserves single historical record. |
| **Operational Impact** | Highly confusing for employers/verifiers scanning old paper copies. | Clean institutional standard; zero ambiguity between old and new copies. | Catastrophic for participants with clerical spelling mistakes in their names. |
| **Database Schema Impact** | 0 migrations required, but introduces severe verification defects. | **Requires updating the unique constraint** via a proposed migration (`m0008`). | 0 migrations required, but operationally unacceptable for an NGO. |

#### Authoritative Decision: Approach B — The Superseded Credential Architecture
Approach B is the **only** cryptographically sound and forensically consistent model. Under this architecture:
1. **Certificate History**: Every issued physical or digital certificate is an immutable historical entity with a permanent identity. An issued certificate is never erased, overwritten, or mutated.
2. **Revoked Credential Behavior**:
   - The revoked certificate remains permanently stored in `certificates` with `status = 'revoked'`.
   - Its original `certificate_number` and `verification_token` are permanently preserved.
   - When the QR code on an old physical certificate or previously downloaded PDF/JPG is scanned, public verification returns **HTTP 200 OK** with an explicit **REVOKED / SUPERSEDED** warning:
     > *"This certificate was officially invalidated and superseded on [Date]. Any physical or digital document bearing this certificate number is void and no longer authentic."*
   - This eliminates any possibility of an invalidated physical printout appearing "valid" or returning a confusing "404 Not Found".
3. **Replacement Credential Behavior**:
   - The corrected certificate is inserted as a **brand-new row** in `certificates`.
   - It receives a **fresh Certificate Number** (e.g. `LC-2026-SPC-7K9MW`).
   - It receives a **fresh 256-bit CSPRNG Verification Token** (`bin2hex(random_bytes(32))`).
   - It records the corrected legal name snapshot (`recipient_name_snapshot`).
   - Its public verification returns **HTTP 200 OK** with an **AUTHENTIC & VERIFIED** badge.
4. **Audit Trail**:
   - Two distinct audit log records are appended:
     - `certificate.revoke`: Captures old certificate ID, old number, reason, and `superseded_by_id`.
     - `certificate.issue`: Captures new certificate ID, new number, and `replaces_id`.

---

### R1.2 Resolution 2: Name Correction & Physical/Digital Consistency

#### The Inconsistency Vulnerability in In-Place Updates
If `recipient_name_snapshot` is modified in-place on an already-issued certificate:
1. An attendee previously downloaded or printed a certificate with Certificate Number `LC-2026-SPC-00100` reading "Jon Doe".
2. The coordinator corrects the name in-place to "Johnathan Doe".
3. A third party (e.g. employer, university admissions officer) is handed the physical document reading "Jon Doe" and scans the QR code.
4. The public verification screen displays: *"Authentic & Verified: Johnathan Doe"*.
5. The verifier observes a discrepancy between the printed credential and the official verification screen, creating immediate suspicion of document tampering or fraud.
6. Conversely, if a malicious individual acquires a discarded physical printout for "Jon Doe", they could claim the verification portal belongs to them if names are silently mutated.

#### Authoritative Rule: The Immutability and Supersession Invariant
Once a certificate has been generated, its `recipient_name_snapshot`, `certificate_number`, and `verification_token` are **strictly immutable**.

When a participant name is corrected:
1. **Existing Certificate**: Transitions permanently to `status = 'revoked'`.
2. **Existing Certificate Number & Token**: Remain permanently bound to the revoked record. They are never reused.
3. **Public Verification of Old Credential**: Reports **REVOKED / SUPERSEDED**. Verifiers scanning old printouts or digital JPGs are immediately informed that the document has been officially superseded and is no longer valid.
4. **Previously Downloaded PDF / JPG**: Rendered void in the real world upon public verification scan.
5. **Replacement Certificate**: Generated as a distinct record with a new certificate number, new token, and the corrected name snapshot.
6. **Audit History**: Immutably connects the two records via `audit_logs`:
   ```json
   {
     "action": "certificate.reissue_name_correction",
     "superseded_cert_id": 14,
     "superseded_cert_number": "LC-2026-SPC-00100",
     "new_cert_id": 18,
     "new_cert_number": "LC-2026-SPC-00142",
     "old_name": "Jon Doe",
     "new_name": "Johnathan Doe",
     "reason": "Clerical spelling correction requested by attendee"
   }
   ```

---

### R1.3 Resolution 3: Public Revocation Privacy Standard

#### The Safeguarding & Privacy Risk
In the context of the Listening Community – Suicide Prevention Campaign (LC-SPC), displaying internal operational or administrative revocation reasons on public verification portals introduces severe privacy and safeguarding hazards.
- Reasons such as *"Participant left after morning crisis workshop"*, *"Disciplinary flag"*, or *"Attendee requested withdrawal due to personal distress"* violate non-clinical boundaries and could expose vulnerable individuals to external stigma if scanned by colleagues, family members, or employers.
- Even generic reasons like *"Administrative error"* invite unnecessary speculation.

#### Authoritative Decision: Strict Generic Public Revocation
The public verification endpoint (`GET /verify/{token}`) will **NEVER** expose the internal `revocation_reason`.

When a revoked certificate token is verified publicly, the page displays strictly:
1. **Status Badge**: `REVOKED / INVALID` (Prominent red alert banner).
2. **Certificate Number**: `LC-2026-SPC-XXXXX`
3. **Recipient Legal Name**: `recipient_name_snapshot`
4. **Event Title**: `events.title`
5. **Certificate Type**: e.g., "Certificate of Participation"
6. **Original Issue Date**: `issue_date`
7. **Revocation Date**: `revoked_at` (formatted as e.g. "September 15, 2026")
8. **Standard Institutional Statement**:
   > *"This certificate was officially invalidated by Listening Community SPC on [Date] and is no longer recognized as an authentic credential. Physical or electronic copies of this document are void."*

**Internal Restriction**:
The actual `revocation_reason` is restricted exclusively to:
- `certificates.revocation_reason` in the database.
- Administrative Certificate Detail view (`/admin/certificates/{id}`) visible **only** to `coordinator` and `super_admin`.
- Immutable `audit_logs` entries.

---

### R1.4 Resolution 4: Flagged Participant Governance Model

In Phase 1D, `participants.status` was designed with three states: `'active'`, `'flagged'`, and `'blocked'`. A participant is `flagged` when administrative discrepancies, suspected identity collisions, or intake concerns require human review.

#### Eligibility Governance Rules for Flagged Participants

1. **Automatic Bulk Issuance Lockout**:
   - In Bulk Certificate Issuance (`POST /admin/events/{id}/certificates/bulk`), flagged participants are **STRICTLY EXCLUDED**.
   - The query filter explicitly enforces `participants.status = 'active'`.
   - A flagged participant can **never** be issued a certificate through an automated bulk batch.
2. **Manual Individual Confirmation Workflow**:
   - To issue a certificate to a flagged attendee, a `coordinator` or `super_admin` must navigate to the individual issuance interface for that specific registration.
   - The interface displays an explicit amber compliance alert:
     > **FLAGGED ATTENDEE NOTICE**: This participant record is currently FLAGGED in the system. Review attendee identity and intake history before authorizing credential generation.
   - The issuance form requires two mandatory inputs:
     1. An explicit confirmation checkbox:  
        `[x] I have verified the attendee's identity and attendance, and officially authorize certificate issuance for this flagged record.`
     2. A non-clinical operational justification note (`flag_override_reason`, minimum 10 characters), screened to reject prohibited clinical/distress terms.
3. **Authorized Roles**:
   - Strictly `coordinator` (Rank 30) and `super_admin` (Rank 40). Staff (Rank 20) and Viewers (Rank 10) are barred with HTTP 403 Forbidden.
4. **Scope of Confirmation**:
   - Confirmation is **per certificate issuance**.
   - It does **not** silently unflag the participant's global account in `participants.status` (which remains governed by Phase 1D participant management).
5. **Action Recorded & Audited**:
   - Logged in `audit_logs` under `action = 'certificate.issue'`:
     ```json
     {
       "cert_id": 22,
       "cert_number": "LC-2026-SPC-8M2PQ",
       "participant_id": 45,
       "participant_status": "flagged",
       "flag_override_authorized": true,
       "flag_override_reason": "Verified attendee identity against government photo ID at desk",
       "authorized_by": 2
     }
     ```
6. **Denial / Unchecked Behavior**:
   - If the confirmation checkbox is unchecked or the justification is missing, the request fails with **HTTP 422 Unprocessable Entity**.
   - If the coordinator decides against issuance, zero records are created. If attendance itself was fraudulent, the coordinator utilizes the Phase 1F attendance update workflow to reclassify attendance to `absent`.
7. **Database Schema Impact**:
   - **Zero new database columns required**.
   - Existing attribution (`certificates.issued_by`) and `audit_logs.metadata` fully capture this operational event.

---

### R1.5 Resolution 5: Final Database Decision

#### Evaluation: Is the existing schema sufficient?

| Architectural Model | Can It Work with 0 Migrations? | Relational & Operational Evaluation |
| :--- | :---: | :--- |
| **Strict Revocation Finality (Approach C)** | **YES** | If a certificate is revoked, it can NEVER be replaced with another certificate of the same type. Corrections can only be done in-place, which violates physical/digital consistency. |
| **Superseded Credential Architecture (Approach B)** | **NO** | To allow a revoked certificate row and a new active replacement row to coexist for the same `(registration_id, type)`, the database constraint `UNIQUE KEY uk_cert_reg_type (registration_id, type)` **must be modernized**. |

#### Authoritative Conclusion
Because the project requires the **Superseded Credential Architecture (Approach B)** to guarantee that previously printed certificates are never silently mutated or rendered as 404 errors, **A DATABASE MIGRATION IS GENUINELY REQUIRED**.

#### Proposed Migration Design: `m0008_update_certificates_unique_constraint.php`

The proposed migration replaces the rigid unconditional unique key `uk_cert_reg_type` with a conditional unique constraint that allows multiple historical `revoked` certificates while strictly guaranteeing that **at most ONE `active` certificate of a given type can exist per registration**.

##### Proposed SQL (MySQL 8.0+ Production Schema):
```sql
-- Migration: m0008_update_certificates_unique_constraint
-- Purpose: Modernize certificate uniqueness to support immutable superseded credentials.

ALTER TABLE `certificates`
    DROP INDEX `uk_cert_reg_type`,
    -- Virtual generated column: equals `type` when status='active', NULL when status='revoked'
    ADD COLUMN `active_type` VARCHAR(30) GENERATED ALWAYS AS (
        CASE WHEN `status` = 'active' THEN `type` ELSE NULL END
    ) VIRTUAL,
    -- Unique index on (registration_id, active_type).
    -- In MySQL, NULL values are not considered equal; unlimited revoked rows can coexist,
    -- while strictly one active row per type is permitted.
    ADD UNIQUE KEY `uk_cert_active_type` (`registration_id`, `active_type`);
```

##### Proposed SQL (SQLite Test Environment):
```sql
DROP INDEX IF EXISTS `uk_cert_reg_type`;
CREATE UNIQUE INDEX `uk_cert_active_type` 
ON `certificates` (`registration_id`, `type`) 
WHERE `status` = 'active';
```

> [!IMPORTANT]
> **CRITICAL RULE**: This migration is documented **strictly as an architectural proposal**.  
> **DO NOT IMPLEMENT OR RUN THIS MIGRATION YET.**  
> Implementation will only occur after explicit user review and authorization.

---

### R1.6 Resolution 6: Dual Output Formats (Printable PDF & Digital JPG)

#### The Clarification & Requirement
The locked Phase 1G architecture mandates that certificate output must support **TWO downloadable formats**:
1. **PDF** &mdash; Official printable certificate.
2. **JPG** &mdash; High-resolution digital / shareable certificate.

#### Architectural Invariants for Dual Output

```
┌─────────────────────────────────────────────────────────────────┐
│               IMMUTABLE CERTIFICATE RECORD                      │
│   id: 104                                                       │
│   certificate_number: LC-2026-SPC-7K9MW                         │
│   verification_token: 4f9b8c2d... (64 hex)                     │
│   recipient_name_snapshot: Priya Sharma                         │
│   issue_date: 2026-09-15                                        │
│   type: participation                                           │
└────────────────┬───────────────────────────────┬────────────────┘
                 │                               │
                 ▼ (Rendered on-demand)          ▼ (Rendered on-demand)
   ┌───────────────────────────┐   ┌───────────────────────────┐
   │     PRINTABLE PDF         │   │   HIGH-RESOLUTION JPG     │
   │  - A4 Landscape           │   │  - 2480x1754 (300 DPI)    │
   │  - Official Vector Layout │   │  - Digital / Social Share │
   │  - Identical Data & Seals │   │  - Identical Data & Seals │
   │  - Identical QR Code URL  │   │  - Identical QR Code URL  │
   └─────────────┬─────────────┘   └─────────────┬─────────────┘
                 │                               │
                 └───────────────┬───────────────┘
                                 │
                                 ▼ (Both QR codes resolve to)
              https://teami.in/LC/verify/4f9b8c2d...
```

1. **Single Source of Truth**:
   - Both PDF and JPG formats are generated dynamically on-demand from the **exact same immutable certificate record**.
   - Both formats present **identical certificate information**:
     - Recipient Legal Name Snapshot (`recipient_name_snapshot`)
     - Certificate Number (`certificate_number`)
     - Certificate Type & Title
     - Event Title, Schedule, and Venue/Format
     - Campaign Branding and Issuing Authority
     - Authorized Signatures
     - Issue Date (`issue_date`)
     - Verification Seal
     - **Identical QR Verification URL**: `https://teami.in/LC/verify/{verification_token}`
2. **Relational & Credential Integrity**:
   - PDF and JPG files **MUST NOT** become separate database records.
   - They **MUST NOT** receive separate certificate numbers or distinct verification tokens.
   - There is strictly one conceptual credential per issuance, represented in two media formats.
3. **On-Demand Generation Strategy (Zero Binary Blobs)**:
   - Prefer on-demand generation over storing heavy binary files (PDF/JPG) in the database or filesystem.
   - **Benefits**:
     - Eliminates file system synchronization anomalies, storage bloat, and orphaned files.
     - Guarantees that any download immediately reflects real-time database state. If a certificate is revoked, subsequent attempts to download active credentials are automatically blocked.
   - **Generation Engines**:
     - **PDF**: Pure HTML5/CSS3 `@media print` landscape canvas (`A4 Landscape`, $297\text{mm} \times 210\text{mm}$) rendered directly via browser print/PDF streaming (`window.print()` and standard download headers).
     - **JPG**: High-density client/server raster engine producing a crisp $2480 \times 1754$ image (A4 at 300 DPI equivalent) via HTML5 Canvas rasterization (`toDataURL('image/jpeg', 0.95)`) or server-side GD on-demand streaming.
4. **Revocation Semantics across Both Formats**:
   - If a participant previously saved or printed either the PDF or JPG representation prior to revocation, that file remains physically on their local storage or paper.
   - However, **any scan of the QR code on either the PDF or the JPG** immediately resolves to `https://teami.in/LC/verify/{token}`, which returns **HTTP 200 REVOKED / INVALID**, warning the verifier that the credential is void.
5. **Replacement Semantics across Both Formats**:
   - When a replacement certificate is issued (e.g. following a name typo correction), a completely new PDF and a completely new JPG are generated from the new certificate record.
   - Both new formats display the new certificate number, new verification token, and new QR code.

---

# Complete Phase 1G Architecture Specification

---

## 1. Executive Summary

Phase 1G establishes the official **Certificate System & Issuance** module for the Listening Community – Suicide Prevention Campaign (LC-SPC). The primary objective is to translate verified physical and virtual event participation into tamper-proof, auditable, and publicly verifiable digital credentials.

The system connects seamlessly into the authoritative Phase 1 domain chain:
$$\text{Campaign} \longrightarrow \text{Event} \longrightarrow \text{Participant} \longrightarrow \text{Event Registration} \longrightarrow \text{Attendance (Phase 1F)} \longrightarrow \mathbf{\text{Certificate (Phase 1G)}}$$

### Core Architectural Invariants
1. **Phase 1F Immutability**: All Phase 1F attendance and check-in code, routes, controllers, and tests remain completely frozen and untouched. Phase 1G acts strictly as a downstream consumer of attendance data.
2. **Attendance as a Prerequisite Gate**: Certificates of type `participation` can **only** be issued to event registrations whose attendance has been physically or virtually verified (`attendance_status = 'attended'`) and whose registration status is `'confirmed'`.
3. **The Superseded Credential Invariant**: Issued physical certificates are immutable legal artifacts. Clerical name corrections revoke the old credential (marking it void upon public scan) and issue a fresh, independently verifiable credential.
4. **Dual Output Support**: Every active certificate supports on-demand downloadable **PDF (Printable)** and **JPG (Digital / Shareable)** formats, generated from the same single record with identical metadata and identical QR tokens.
5. **Cryptographic Verification**: Every certificate receives a dual-identifier architecture:
   - A clean, human-readable **Certificate Number** (`LC-YYYY-SPC-XXXXX`) suitable for physical certificates and resumes.
   - An unpredictable, 256-bit CSPRNG **Public Verification Token** (`VARCHAR(64)`) embedded solely in the public verification QR code URL (`https://teami.in/LC/verify/{token}`).
6. **Strict Safeguarding & Non-Clinical Boundaries**: The system strictly enforces the project-wide privacy charter:
   - Zero clinical, psychological, distress, counseling, suicide-risk, or medication data.
   - Zero public disclosure of revocation reasons, participant email, phone number, or internal database IDs.
   - Three-tier Privacy Shield for internal roles (`viewer`, `staff`, `coordinator`).
7. **Append-Only Auditability**: All certificate actions (`issue`, `bulk_issue`, `revoke`, `reissue_name_correction`, `download`) are recorded in `audit_logs` without leaking raw bearer verification tokens.

---

## 2. Existing Schema Analysis

The baseline migration in `database/migrations/m0006_create_certificates_table.php` established:

```sql
CREATE TABLE IF NOT EXISTS `certificates` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `certificate_number` VARCHAR(50) NOT NULL,
    `verification_token` VARCHAR(64) NOT NULL,
    `registration_id` BIGINT UNSIGNED NOT NULL,
    `recipient_name_snapshot` VARCHAR(150) NOT NULL,
    `type` ENUM('participation', 'volunteer', 'speaker', 'appreciation') NOT NULL DEFAULT 'participation',
    `issue_date` DATE NOT NULL,
    `status` ENUM('active', 'revoked') NOT NULL DEFAULT 'active',
    `issued_by` INT UNSIGNED NULL DEFAULT NULL,
    `revoked_at` DATETIME NULL DEFAULT NULL,
    `revoked_by` INT UNSIGNED NULL DEFAULT NULL,
    `revocation_reason` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_cert_number` (`certificate_number`),
    UNIQUE KEY `uk_cert_token` (`verification_token`),
    UNIQUE KEY `uk_cert_reg_type` (`registration_id`, `type`),
    INDEX `idx_cert_status` (`status`),
    INDEX `idx_cert_issued_by` (`issued_by`),
    INDEX `idx_cert_revoked_by` (`revoked_by`),
    CONSTRAINT `fk_cert_registration` FOREIGN KEY (`registration_id`) REFERENCES `event_registrations` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cert_issued_by` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_cert_revoked_by` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Field-by-Field Classification

| Field Name | Type & Nullability | Classification | Architectural Evaluation |
| :--- | :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | **KEEP** | Primary key. |
| `certificate_number` | `VARCHAR(50) NOT NULL` | **KEEP** | Public-facing human-readable identifier (e.g. `LC-2026-SPC-9N4KD`). Enforced unique via `uk_cert_number`. |
| `verification_token` | `VARCHAR(64) NOT NULL` | **KEEP** | 256-bit CSPRNG hex bearer token for public QR verification. Enforced unique via `uk_cert_token`. |
| `registration_id` | `BIGINT UNSIGNED NOT NULL` | **KEEP** | Foreign key referencing `event_registrations.id`. `ON DELETE RESTRICT` guarantees registrations cannot be deleted while certificates exist. |
| `recipient_name_snapshot` | `VARCHAR(150) NOT NULL` | **KEEP** | Freezes the recipient's legal name at issuance, ensuring immutability. |
| `type` | `ENUM('participation', 'volunteer', 'speaker', 'appreciation')` | **KEEP** | Certificate category. |
| `issue_date` | `DATE NOT NULL` | **KEEP** | Official date printed on the credential. |
| `status` | `ENUM('active', 'revoked')` | **KEEP** | Binary lifecycle state machine. |
| `issued_by` | `INT UNSIGNED NULL` | **KEEP** | Attribution to the Coordinator who generated the certificate. `ON DELETE SET NULL`. |
| `revoked_at` | `DATETIME NULL` | **KEEP** | Revocation timestamp. |
| `revoked_by` | `INT UNSIGNED NULL` | **KEEP** | Attribution to the Administrator who invalidated the credential. `ON DELETE SET NULL`. |
| `revocation_reason` | `VARCHAR(255) NULL` | **KEEP** | Mandatory internal operational reason. Restricted to admin views and audit logs. |
| `created_at` / `updated_at` | `DATETIME NOT NULL` | **KEEP** | Timestamps tracking creation and modification. |
| `uk_cert_reg_type` | `UNIQUE KEY (registration_id, type)` | **MODIFY (Proposed m0008)** | Modernized to `uk_cert_active_type` via virtual column to allow superseded revoked certificates to coexist with active replacements. |

---

## 3. Certificate Eligibility Rules

```
[Event Eligibility] ──> [Registration Status] ──> [Participant Status] ──> [Attendance Status] ──> [Type Gating]
```

### Eligibility Evaluation Matrix

| Domain Check | Condition Required | Pass State | Rejection Behavior |
| :--- | :--- | :--- | :--- |
| **Event State** | `events.deleted_at IS NULL` | Active event | HTTP 404 Event Not Found |
| **Event Status** | `events.status IN ('published', 'ongoing', 'completed')` | Eligible | HTTP 403 Event in Draft or Cancelled status |
| **Event Timeline** | `NOW() >= events.start_time` | Started or Finished | HTTP 422 Cannot issue certificate before event starts |
| **Registration State** | `event_registrations.id` exists | Registered | HTTP 404 Registration Not Found |
| **Registration Status** | `event_registrations.status === 'confirmed'` | Confirmed | HTTP 403 Registration is Pending, Waitlisted, or Cancelled |
| **Participant Status** | `participants.status === 'active'` | Active | Blocked &rarr; HTTP 403; Flagged &rarr; Manual confirmation required |
| **Attendance Status** | Evaluated per certificate type (see below) | Attended | HTTP 422 Attendance condition not satisfied |
| **Active Uniqueness** | No active certificate exists for this `(registration_id, type)` | Eligible | HTTP 409 Conflict: Certificate already active |

### Explicit Rules by Attendance Status
1. **`attended`**: **ELIGIBLE** for all certificate types (`participation`, `volunteer`, `speaker`, `appreciation`). The attendee's physical or virtual presence was verified at check-in during Phase 1F.
2. **`unmarked`**: **STRICTLY INELIGIBLE** for `participation` certificates (HTTP 422). The attendee never checked in.
3. **`absent`**: **STRICTLY INELIGIBLE** for all certificate types (HTTP 422).
4. **`excused`**: **STRICTLY INELIGIBLE** for `participation` certificates (HTTP 422). Legitimate absence does not substitute for actual attendance.
5. **`cancelled` Registration**: **STRICTLY INELIGIBLE** (HTTP 403).
6. **`pending` / `waitlisted` Registration**: **STRICTLY INELIGIBLE** (HTTP 403).

---

## 4. Certificate Types

### Type Matrix & Operational Permissions

| Type Slug | Certificate Title | Eligible Profile | Attendance Requirement | Authorized Issuers |
| :--- | :--- | :--- | :--- | :--- |
| **`participation`** | Certificate of Participation | Verified attendees | Mandatory (`attendance_status = 'attended'`) | `coordinator`, `super_admin` |
| **`volunteer`** | Certificate of Volunteer Service | Event support volunteers | Mandatory (`attendance_status = 'attended'`) | `coordinator`, `super_admin` |
| **`speaker`** | Certificate of Facilitation / Speaker | Resource persons, doctors, trainers | Mandatory (`attendance_status = 'attended'`) | `coordinator`, `super_admin` |
| **`appreciation`** | Certificate of Appreciation | Community partners, sponsors | Confirmed Registration | `coordinator`, `super_admin` |

*Multi-Type Invariant*: A registration may receive at most one active certificate of each distinct type (e.g. 1 `participation` AND 1 `volunteer`).

---

## 5. Certificate Number & Token Design

```
┌─────────────────────────────────────────────────────────────┐
│                    OFFICIAL CREDENTIAL                      │
│                                                             │
│   Display Identifier:       LC-2026-SPC-7K9MW               │
│   (Human-readable, printed on document, resume-safe)        │
│                                                             │
│   Verification Bearer:      4f9b8c2d... (64 hex characters) │
│   (256-bit CSPRNG entropy, embedded ONLY in QR URL)         │
└─────────────────────────────────────────────────────────────┘
```

1. **Human-Readable Certificate Number**:
   - Format: `LC-{YYYY}-SPC-{5_CHAR_CROCKFORD}` (e.g. `LC-2026-SPC-9N4KD`)
   - 5 Crockford Base32 characters ($32^5 = 33,554,432$ combinations/year, excluding `0, O, 1, I`).
   - Non-sequential; printed on both physical PDF and digital JPG certificates.
2. **Cryptographic Verification Token**:
   - Format: 64-character lowercase hexadecimal string (`bin2hex(random_bytes(32))`).
   - 256 bits of CSPRNG entropy ($2^{256} \approx 1.15 \times 10^{77}$ combinations).
   - Embedded solely into the QR verification URL (`https://teami.in/LC/verify/{token}`).
   - Treated as a bearer secret; never exposed in plain text in audit logs.

---

## 6. Certificate Lifecycle

```
       [ Eligible Attendee ]
                 │
                 │ POST /admin/events/{id}/certificates/issue
                 ▼
       ┌───────────────────┐
       │      ACTIVE       │ ──── Clerical Name Correction ──────────┐
       └─────────┬─────────┘                                          │
                 │                                                    │
                 │ POST /admin/certificates/{id}/revoke               │
                 ▼                                                    ▼
       ┌───────────────────┐                                ┌───────────────────┐
       │      REVOKED      │ ◄──────────────────────────────│  REVOKED (OLD)    │
       │ (Terminal State)  │                                └───────────────────┘
       └───────────────────┘                                          │
                                                                      │ Issues Fresh
                                                                      ▼
                                                            ┌───────────────────┐
                                                            │  ACTIVE (REPLACED)│
                                                            └───────────────────┘
```

---

## 7. Re-Issuance & Revocation

1. **Clerical Name Correction (Supersession)**:
   - Old certificate row transitions to `status = 'revoked'` with internal reason: `"Superseded by replacement certificate due to clerical name correction"`.
   - New certificate row inserted with corrected `recipient_name_snapshot`, new `certificate_number`, new `verification_token`, and `status = 'active'`.
   - Old physical paper / JPG QR scan returns: **REVOKED / SUPERSEDED** (document void).
   - New physical paper / JPG QR scan returns: **AUTHENTIC & VERIFIED** (correct name).
2. **Category Reclassification**:
   - Old certificate (e.g. `participation`) revoked.
   - New certificate of different type (`volunteer`) issued under the registration.
3. **Disciplinary Revocation**:
   - Certificate transitioned to `status = 'revoked'`.
   - Revocation is terminal. Public verification warns the credential was invalidated.

---

## 8. Role-Based Access Control (RBAC) Matrix

$$\text{super\_admin (40)} > \text{coordinator (30)} > \text{staff (20)} > \text{viewer (10)}$$

| Action / Feature | `viewer` (10) | `staff` (20) | `coordinator` (30) | `super_admin` (40) | Public / Anon |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Public QR Verification (`/verify/{token}`)** | &check; | &check; | &check; | &check; | &check; *(Rate-Limited)* |
| **Certificate Directory** | &check; *(De-identified)* | &check; *(Masked PII)* | &check; *(Full PII)* | &check; *(Full PII)* | &#10007; |
| **Certificate Detail View** | &check; *(De-identified)* | &check; *(Masked PII)* | &check; *(Full PII)* | &check; *(Full PII)* | &#10007; |
| **Download Official PDF** | &#10007; | &check; | &check; | &check; | &#10007; |
| **Download Digital JPG** | &#10007; | &check; | &check; | &check; | &#10007; |
| **Issue Single Certificate** | &#10007; | &#10007; | &check; | &check; | &#10007; |
| **Bulk Issue Certificates** | &#10007; | &#10007; | &check; | &check; | &#10007; |
| **Correct Name / Re-issue** | &#10007; | &#10007; | &check; | &check; | &#10007; |
| **Revoke Certificate** | &#10007; | &#10007; | &check; | &check; | &#10007; |
| **Confirm Flagged Attendee** | &#10007; | &#10007; | &check; | &check; | &#10007; |

---

## 9. Bulk Certificate Issuance Architecture

1. **Filtering**: Selects `event_registrations` with `status = 'confirmed'`, `attendance_status = 'attended'`, `participants.status = 'active'`, and no active certificate of target type.
2. **Chunking**: Maximum batch size of **100 certificates** per execution.
3. **Transactional Integrity**: Wrapped in `Database::beginTransaction()` and `commit()`.
4. **Collision Handling**: Retries token generation on collision; rollback on fatal error.

---

## 10. Dual Output Generation Architecture (Printable PDF & Digital JPG)

The locked architecture requires on-demand generation for two distinct media formats from a single database record:

### 10.1 Format Comparison & Specifications

| Dimension | PDF (Official Printable Certificate) | JPG (Digital / Shareable Certificate) |
| :--- | :--- | :--- |
| **Primary Use Case** | Physical printout, framing, official university/employer submission. | Social sharing (LinkedIn, WhatsApp), digital portfolios, mobile storage. |
| **Dimensions / Canvas** | Standard A4 Landscape ($297\text{mm} \times 210\text{mm}$). | $2480 \times 1754$ pixels (300 DPI high-resolution landscape). |
| **File Compression / Type** | Portable Document Format (vector text/SVG). | High-quality JPEG (`image/jpeg`, 95% quality). |
| **Rendering Engine** | Native HTML5/CSS3 `@media print` layout & browser stream. | High-resolution Canvas rasterizer or server GD on-demand stream. |
| **Data Source** | `certificates` table record. | Exact same `certificates` table record. |
| **Certificate Number** | Identical (`certificate_number`). | Identical (`certificate_number`). |
| **Verification Token / QR** | Identical (`verification_token`). | Identical (`verification_token`). |
| **QR Code Destination** | `https://teami.in/LC/verify/{token}` | `https://teami.in/LC/verify/{token}` |

### 10.2 Generation Rules
- **Zero Binary Storage**: Neither PDF nor JPG files are stored as binary BLOBs in the database or cached permanently on disk. They are rendered dynamically on demand upon request.
- **Revocation Protection**: If a certificate is revoked, subsequent attempts to access the download endpoints are blocked with HTTP 403/422. If a previously downloaded copy is retained locally by a user, scanning its embedded QR code returns the public **REVOKED / INVALID** warning.
- **Replacement Generation**: When a replacement certificate is authorized, a completely new PDF and a completely new JPG are generated from the new record, featuring the new certificate number, new token, and new QR code.

---

## 11. QR Verification Architecture

- **Payload**: Contains strictly `https://teami.in/LC/verify/{verification_token}`.
- **Zero PII**: No names, emails, phones, or database IDs encoded in QR.
- **Renderer**: In-project pure PHP SVG vector QR generator (`App\Core\QrCode`), fully offline, CSP-compliant, and embedded identically in both PDF and JPG formats.

---

## 12. Public Verification Architecture

- **Route**: `GET /verify/{token}` (and alias `GET /certificate/verify/{token}`).
- **Rate Limiting**: Max 10 failed attempts per IP per 15 minutes (`Retry-After: 900`).
- **Disclosures**:
  - Active: Authentic badge, certificate number, recipient name snapshot, event title/date, type, issuing organization.
  - Revoked: Revocation badge, certificate number, recipient name snapshot, event title/date, revocation date, generic invalidation statement. Actual reason is strictly hidden.
  - Not Found: Minimal disclosure HTTP 404 page.

---

## 13. Security Architecture

1. **Anti-CSRF**: All POST actions enforce `CsrfMiddleware`.
2. **Unguessable Tokens**: 256 bits of entropy prevents enumeration.
3. **Prohibited Keyword Scrubbing**: Reasons screened to reject clinical/distress terms.
4. **Credential Masking**: Verification tokens masked in audit logs (`tok_****[last 4 chars]`).

---

## 14. Audit Architecture

Appends to `audit_logs`:
- `certificate.issue`
- `certificate.bulk_issue`
- `certificate.revoke`
- `certificate.reissue_name_correction`
- `certificate.download` (records format: `pdf` or `jpg`)

---

## 15. Route Architecture

### Public Routes
- `GET /verify/{token}` &bull; `Public\CertificateVerifyController@show` &bull; Public / Rate-Limited
- `GET /certificate/verify/{token}` &bull; Alias

### Admin Routes (`/admin/*`)
- `GET /admin/certificates` &bull; `viewer` (10) &bull; Directory
- `GET /admin/events/{id}/certificates` &bull; `viewer` (10) &bull; Event Hub
- `POST /admin/events/{id}/certificates/issue` &bull; `coordinator` (30) &bull; CSRF &bull; Single Issue
- `POST /admin/events/{id}/certificates/bulk` &bull; `coordinator` (30) &bull; CSRF &bull; Bulk Issue
- `GET /admin/certificates/{id}` &bull; `viewer` (10) &bull; Detail View
- `GET /admin/certificates/{id}/print` &bull; `staff` (20) &bull; Vector Print Layout
- `GET /admin/certificates/{id}/download` &bull; `staff` (20) &bull; Query param `?format=pdf|jpg`
- `GET /admin/certificates/{id}/pdf` &bull; `staff` (20) &bull; Direct PDF Stream / Print
- `GET /admin/certificates/{id}/jpg` &bull; `staff` (20) &bull; Direct High-Res JPG Download
- `POST /admin/certificates/{id}/reissue` &bull; `coordinator` (30) &bull; CSRF &bull; Name Correction
- `POST /admin/certificates/{id}/revoke` &bull; `coordinator` (30) &bull; CSRF &bull; Revocation

---

## 16. UI / UX Architecture

- **Sidebar Integration**: Direct link to `/admin/certificates`.
- **Event Show Action**: Direct button to `/admin/events/{id}/certificates`.
- **Attendance Roster Action**: Direct button next to attended attendees.
- **Dual Format Download Controls**:
  - Certificate detail and print layouts render two clear action buttons:
    - `[ Download Official PDF (Print) ]`
    - `[ Download Digital JPG (Share) ]`
- **Modals**: Name Correction Modal, Revocation Modal, Flagged Confirmation Modal.

---

## 17. Reporting

- Certificates Issued vs. Turnout Conversion Rate.
- Active vs. Revoked distribution.
- Issuance by Certificate Type.
- Download Breakdown: PDF vs. JPG downloads.

---

## 18. Privacy Model

| Field | `viewer` | `staff` | `coordinator` / `super_admin` | Public (`/verify/{token}`) |
| :--- | :--- | :--- | :--- | :--- |
| Recipient Name | `[De-identified]` | `Priya Sharma` | `Priya Sharma` | `Priya Sharma` |
| Contact Email/Phone | `[De-identified]` | Masked | Unmasked | **EXCLUDED** |
| Certificate Number | Masked | Unmasked | Unmasked | Unmasked |
| Verification Token | **HIDDEN** | **HIDDEN** | **HIDDEN** | Bearer URL only |
| Revocation Reason | Sanitized generic | Sanitized generic | Full internal text | **EXCLUDED** (Generic statement) |

---

## 19. Database Decision

### **DECISION: SCHEMA MIGRATION PROPOSED (m0008)**

To support the **Superseded Credential Architecture** without violating unique constraints or allowing old physical documents to remain silently inconsistent with online verification:
- Migration `m0008_update_certificates_unique_constraint.php` is proposed.
- Replaces rigid `UNIQUE (registration_id, type)` with conditional unique constraint `uk_cert_active_type`.
- **Zero code or migration files created during this architecture phase.**

---

## 20. Integration Map

$$\text{Campaigns} \longrightarrow \text{Events} \longrightarrow \text{Participants} \longrightarrow \text{Registrations} \longrightarrow \text{Attendance} \longrightarrow \mathbf{\text{Certificates}} \longrightarrow \text{Audit Logs}$$

---

## 21. Test Matrix (40 Planned Assertions)

1. Eligibility: `attended` registration receives certificate.
2. Ineligibility: `unmarked` registration rejected (HTTP 422).
3. Ineligibility: `absent` registration rejected (HTTP 422).
4. Ineligibility: `excused` registration rejected (HTTP 422).
5. Ineligibility: `cancelled` registration rejected (HTTP 403).
6. Ineligibility: `pending` registration rejected (HTTP 403).
7. Ineligibility: `waitlisted` registration rejected (HTTP 403).
8. Ineligibility: `blocked` participant rejected (HTTP 403).
9. Flagged Participant: Bulk issuance skips flagged attendee.
10. Flagged Participant: Individual issuance without confirmation checkbox rejected (HTTP 422).
11. Flagged Participant: Individual issuance with confirmation succeeds and logs audit metadata.
12. Event Gating: Draft/cancelled event rejected (HTTP 403).
13. Event Timeline: Pre-event issuance rejected (HTTP 422).
14. Uniqueness: Duplicate active certificate of same type rejected (HTTP 409).
15. Multi-Type: Same registration receives 1 `participation` + 1 `volunteer` certificate.
16. Number Format: Matches `LC-{YYYY}-SPC-{5_CROCKFORD}`.
17. Token Entropy: Exactly 64 hex characters (256-bit CSPRNG).
18. Dual Output (PDF & JPG): Both formats download on-demand from the identical certificate record.
19. Dual Output Consistency: PDF and JPG render identical certificate number, legal name snapshot, and QR code URL.
20. Dual Output Relational Invariant: Downloading PDF and JPG creates zero duplicate rows and zero duplicate tokens in database.
21. Public Verification (Active): HTTP 200 with authentic badge.
22. Public Minimal Disclosure: Zero email, phone, or internal IDs exposed.
23. Public Verification (Revoked): HTTP 200 with revocation badge, revocation date, and generic statement.
24. Revocation Privacy: Actual revocation reason is NOT exposed on public verification page.
25. Public Verification (Invalid): HTTP 404 with minimal disclosure.
26. Rate Limiting: 11th failed verification triggers HTTP 429 and `Retry-After: 900`.
27. Superseded Re-issuance: Name correction revokes old certificate and creates fresh certificate with new number and token.
28. Superseded Old Scan: Scanning old QR (whether from PDF or JPG) returns REVOKED / SUPERSEDED status.
29. Superseded New Scan: Scanning new QR returns AUTHENTIC & VERIFIED with corrected name.
30. Bulk Issuance: Atomically issues batch of certificates for eligible attendees.
31. Bulk Capping: Enforces max batch limit of 100.
32. Bulk Idempotency: Re-running bulk issuance issues 0 duplicates.
33. RBAC: `staff` role cannot issue certificate (HTTP 403).
34. RBAC: `staff` role cannot revoke certificate (HTTP 403).
35. RBAC: `staff` role can download both PDF and JPG formats.
36. RBAC: `viewer` role cannot download/print certificate (HTTP 403).
37. CSRF: State-changing endpoints reject missing CSRF token (HTTP 419/403).
38. QR Vector: Pure PHP SVG QR generator outputs valid vector XML resolving to `/verify/{token}`.
39. Invariants: Certificate operations never mutate `events.capacity` or `event_registrations.status`.
40. Database Hygiene: Zero lingering test rows across all tables.

---

## 22. Failure & Edge Cases

1. **Discarded Paper / Saved JPG Misuse**: Prevented because old paper and digital copies explicitly display "REVOKED / SUPERSEDED" upon public verification scan.
2. **Concurrent Bulk Requests**: Prevented by conditional unique index and database transaction.
3. **Database Disconnections**: Rollback ensures zero partial batches or orphaned records.

---

## 23. Open Architectural Decisions

1. **Public Attendee Certificate Access**:
   - Should confirmed attendees who look up their existing public pass (`/registration/pass/{code}`) see a link to view/download their certificate once issued?
   - *(Recommended: **YES** &mdash; empowers participants to access both PDF and JPG credentials without requiring login credentials).*
2. **Signatory Attribution**:
   - Should certificate signatures default dynamically to `events.coordinator_id` and the organization campaign director from config?
   - *(Recommended: **YES** &mdash; maintains consistency with event coordinator assignment).*
3. **OpenGraph Social Sharing Metadata**:
   - Should `/verify/{token}` provide OpenGraph tags (title: "Verified Certificate of Participation — LC-SPC") for clean previews on LinkedIn/WhatsApp?
   - *(Recommended: **YES** with minimal disclosure).*

---

## 24. Final Recommendation

1. **Approve Revision 1 Resolutions**: Lock the Superseded Credential Architecture, generic public revocation privacy, flagged participant governance, and dual downloadable output formats (PDF & JPG).
2. **Approve Proposed Migration `m0008`**: Authorize creation of `m0008_update_certificates_unique_constraint.php` upon implementation kickoff.
3. **Proceed to Implementation** upon your explicit authorization.
