# Müller Holding AG Intranet – Verbindlicher Bauplan

Zielsystem: Laravel 12 (PHP 8.4), MariaDB in Produktion, SQLite in Tests.
Repo: /home/user/mHAG-intern. Sprache der UI: Deutsch. App-Name: "Müller Holding AG Intranet".
Produktions-URL: https://intranet.mueller-holding.ag (nur über APP_URL, nie hart codieren).

## Corporate Identity (verbindlich, aus Skill mhag-ci)
- Primärakzent Senfgold #E3AC48, Logo-Grau #9F9F9F, Anthrazit #2E2D2E, Beige #FBF6EC, Weiß #FFFFFF.
- Schrift: Carlito, Calibri, 'Segoe UI', sans-serif.
- Gestaltung "Goldpunkt": dünne Haarlinien (#DDDBD6), kurze Goldbalken unter Überschriften,
  anthrazitfarbenes Fußband, Versal-Labels in Grau mit Laufweite. Viel Weißraum, keine Schatten/Verläufe.
- Pflichtangaben-Footer (immer 2 Zeilen, nie umformulieren):
  Zeile 1: Müller Holding AG · Rheinpromenade 13 · 40789 Monheim am Rhein · kontakt@mueller-holding.ag · mueller-holding.ag
  Zeile 2: Sitz: Monheim am Rhein · Registergericht: Amtsgericht Düsseldorf · HRB 104291 · Vorstand: Timo Müller · Aufsichtsratsvorsitzender: Jan Walprecht
- Logo liegt unter public/images/logo-mhag.jpg (Briefkopf/Navbar). CSS-Variablen in resources/css/app.css.

## Statuslogik (systemweit, Blade-Komponente <x-status-badge>)
severity: danger (rot #B3261E), warning (orange #B77400), success (grün #1E7B34), info (blau #1D5FA6), neutral (grau).
Farbe nie allein: immer Icon + Text (Badge-Komponente erledigt das). Emoji-Punkte 🔴🟠🟢🔵 nur in Dashboard-"Heute relevant".

## Kernprinzipien (nicht verhandelbar)
1. SOLL vs. IST strikt getrennt; jeder IST-Wert hat `origin` (assumed|manual_confirmed|manual_entered|bank_import|corrected|cancelled).
2. Systemseitig angenommene Zahlungen ("assumed") sind NIE mit bestätigten gleichzusetzen; UI kennzeichnet Herkunft immer.
3. Keine stillen Finanzkorrekturen: nur Storno/Gegenbuchung/Korrekturbuchung, Historie bleibt.
4. Wirkungsdatum (effective_date) vs. Erfassungsdatum (created_at/recorded_at) überall getrennt.
5. Abgeleitete Werte (offenes Kapital, Aktienbestand) werden aus Transaktionen berechnet, nie als zweite manuelle Wahrheit gespeichert.
6. Geldbeträge DECIMAL(18,2), Zinssätze DECIMAL(9,6), Quoten DECIMAL(9,6). Nie float. In PHP: BCMath/strings über Money-Helper (App\Support\Money, bcscale 2 bzw. 6).
7. Audit-Trail für jede kritische Aktion (App\Services\AuditService::log()).
8. Rechtliche Zurückhaltung: keine Aussagen über Rechtswirksamkeit; keine erfundenen Daten/Zinssätze.
9. RBAC via spatie/laravel-permission; Datenscope zusätzlich über user_entity_assignments (externe sehen nur zugeordnete Daten).
10. Historische Snapshots (PDFs, Aktionärslisten) sind unveränderlich.

## Namenskonventionen
- Alle Models in app/Models, Services in app/Services, Enums (PHP backed enums, string) in app/Enums.
- Nummernkreise über App\Services\NumberSequenceService (settings-Tabelle): DAR-{JAHR}-{LFD5}, AR-{JAHR}-{LFD3}, VOR-, HV-, UB-, SB- (sonstiger Beschluss), DOK-, AL- (Aktionärsliste).
- Beträge-Formatierung: App\Support\Money::format() → "1.234,56 EUR"; Datum: d.m.Y über Helfer format_date().
- Blade-Layout: resources/views/layouts/app.blade.php (Sidebar-Navigation gem. §134), Komponenten unter resources/views/components.

## Migrations-Zeitfenster (Prefix, damit parallel gearbeitet werden kann)
- 0001_01_00_*  Framework (users etc., von Laravel generiert, angepasst)
- 2026_01_01_*  Foundation: roles/permissions (spatie), audit_logs, settings, user_entity_assignments, user_invitations, login_attempts, two_factor (in users), notifications, reminders, daily_facts, faq_entries, changelog_entries
- 2026_02_01_*  Stammdaten: entities, persons, companies, addresses, contact_details, bank_accounts, tax_details, identity_documents, entity_relationships, organization_roles
- 2026_03_01_*  Darlehen: loan_types, loans, loan_status_history, loan_interest_terms, loan_fees, loan_disbursements, repayment_plan_items, payments, payment_allocations, loan_transactions, loan_recalculations, securities, guarantees
- 2026_04_01_*  Dokumente/Verträge: documents, document_versions, document_links, contract_templates, contract_template_versions, contracts, contract_amendments
- 2026_05_01_*  Holding: shareholders, share_transactions, shareholder_list_snapshots, investments, corporate_bodies, corporate_body_members, resolutions, resolution_participants, resolution_votes, resolution_links, signature_requests, signature_participants

## Datenbank-Schema (Kernfelder; Agenten dürfen sinnvolle Felder ergänzen, nichts umbenennen)

### Foundation
- users: id, entity_id nullable FK entities, name, email unique, password nullable (Einladung), is_active bool, two_factor_secret text null (encrypted), two_factor_recovery_codes text null (encrypted, gehasht je Code), two_factor_confirmed_at, last_login_at, locale default 'de', privacy_mode bool default 0, timestamps, softDeletes
- user_entity_assignments: id, user_id FK, entity_id FK, context ('self','company','supervisory_board'), label, is_default bool, timestamps
- user_invitations: id, user_id FK nullable, entity_id FK, email, token_hash (sha256), roles json, expires_at, accepted_at, revoked_at, invited_by FK users, timestamps
- login_attempts: id, email, user_id nullable, ip, user_agent, successful bool, created_at
- audit_logs: id, user_id nullable, action string, auditable_type/auditable_id nullable morph, ip, user_agent, old_values json null, new_values json null, context json null, created_at (kein update!)
- settings: id, group, key unique-with-group, value json, timestamps
- reminders (Wiedervorlagen): id, title, description, due_date date, due_time nullable, assigned_to FK users, priority enum(low,normal,high), status enum(open,done,cancelled), remindable morph nullable, created_by, timestamps
- notifications: Laravel-Standard (database channel)
- daily_facts: id, date_month_day char(5) '08-30' oder full date, title, description, source, country, recurring bool, is_active bool
- faq_entries: id, category, question, answer text, sort, visibility enum(all,internal,admin,supervisory_board,lender,borrower), is_active, timestamps
- changelog_entries: id, version, released_on date, changes text (markdown), timestamps

### Stammdaten
- entities: id, type enum(person,company,organization), display_name (gepflegt via Model), status enum(active,archived), internal_number, tags json, notes text, timestamps, softDeletes
- persons: id, entity_id FK unique, salutation, title, first_name, middle_names, last_name, birth_name, date_of_birth, place_of_birth, nationality, gender, marital_status, timestamps
- companies: id, entity_id FK unique, name, short_name, legal_form, founded_on, register_court, register_number, commercial_register, tax_number, vat_id, business_id, website, email, phone, fax, industry, timestamps
- addresses: id, entity_id FK, type enum(main,secondary,business,correspondence,historical), street, house_number, addition, postal_code, city, state, country default 'Deutschland', valid_from, valid_until, is_primary bool, timestamps
- contact_details: id, entity_id FK, type enum(email,email_alt,phone,mobile,fax,other), value, label, is_primary, timestamps
- bank_accounts: id, entity_id FK, account_holder, iban, bic, bank_name, currency default EUR, is_primary, is_active, note, timestamps
- tax_details: id, entity_id FK, tax_id, tax_number, tax_office, note, timestamps
- identity_documents: id, entity_id FK, type enum(id_card,passport,residence_permit,drivers_license,other), document_number, issued_on, expires_on, authority, country, verified bool, verified_at, verified_by, note, timestamps (Dateien via document_links)
- entity_relationships: id, entity_a_id FK, entity_b_id FK, relationship_type enum(parent,subsidiary,sister,investment,joint_venture,affiliated,other), share_percentage decimal(9,6) null, share_count int null, valid_from, valid_until, note, timestamps
- organization_roles: id, company_entity_id FK entities, person_entity_id FK entities, role enum(managing_director,board_member,authorized_officer,supervisory_board_member,supervisory_board_chair,advisory_board,shareholder,stockholder,contact_person), started_on, ended_on, is_active, sole_representation bool null, representation_rule string, exemption_181 bool null, note, timestamps  — NIE überschreiben, Historie via ended_on.

### Darlehen
- loan_types: id, name, code, description, is_active
- loans: id, loan_number unique, title, lender_entity_id FK, borrower_entity_id FK, loan_type_id FK, contract_basis, contract_date, effective_from (Wirkungsbeginn), disbursement_date nullable, term_months nullable, due_date nullable, notice_period, contract_end nullable, principal_amount decimal(18,2), credit_limit decimal(18,2) nullable, currency default EUR, interest_method enum(act_365,act_360,thirty_360,act_act), interest_frequency enum(monthly,quarterly,semiannual,annual,at_maturity,custom), repayment_model enum(bullet,installment,annuity,custom,open_ended,frame,current_account), default_interest_rate decimal(9,6) null, default_interest_enabled bool, status enum(draft,contract_prepared,for_signature,signed,disbursement_planned,active,partially_repaid,repaid,deferred,terminated,overdue,dunning,legal,defaulted,written_off,archived), risk_rating enum(very_low,low,medium,elevated,high) null, handler_user_id null, project, cost_center, tags json, internal_notes text, timestamps, softDeletes
- loan_status_history: id, loan_id FK, from_status, to_status, changed_by, note, effective_date, created_at
- loan_interest_terms: id, loan_id FK, rate decimal(9,6), valid_from date, valid_until date null, note, timestamps  (Staffelzins = mehrere Zeilen; zinslos = rate 0)
- loan_fees: id, loan_id FK, type enum(processing,commitment,contract,administration,extension,other), name, amount decimal(18,2) null, percentage decimal(9,6) null, recurrence enum(one_time,monthly,quarterly,annual), due_date null, timestamps
- loan_disbursements: id, loan_id FK, planned_amount decimal(18,2), actual_amount decimal(18,2) null, planned_date, actual_date null, status enum(planned,assumed,confirmed,partial,failed,cancelled), origin enum(assumed,manual_confirmed,manual_entered,bank_import,corrected,cancelled) default assumed, bank_account_id null, reference, note, recorded_at, timestamps
- repayment_plan_items: id, loan_id FK, item_type enum(interest,principal,fee), due_date, planned_amount decimal(18,2), actual_amount decimal(18,2) null, status enum(planned,assumed,confirmed,partial,missed,late,waived,cancelled), origin (wie oben) default assumed, actual_date null, value_date null, comment, timestamps  — Der Zahlungsplan. „offen" = planned - COALESCE(actual,plan-Annahme-Logik).
- payments: id, loan_id FK, payer_entity_id, payee_entity_id, payment_date, value_date, amount decimal(18,2), direction enum(incoming,outgoing), bank_account_id null, purpose, reference, origin (wie oben), status enum(recorded,cancelled), cancelled_by/cancelled_at/cancel_reason, note, timestamps
- payment_allocations: id, payment_id FK, bucket enum(costs,fees,default_interest,interest,principal,other), amount decimal(18,2), repayment_plan_item_id null, timestamps
- loan_transactions (Darlehenskonto): id, loan_id FK, booking_type enum(disbursement,repayment,interest_charge,interest_payment,fee_charge,fee_payment,default_interest,cancellation,correction,write_off,other), booking_date, effective_date, amount decimal(18,2) SIGNED (Forderungssicht: + erhöht Forderung, − reduziert), description, source morph nullable, reversal_of nullable FK self, created_by, created_at  — append-only!
- loan_recalculations: id, loan_id FK, trigger_action, triggered_by, earliest_affected_date, old_state json, new_state json, status enum(ok,error), error text null, duration_ms, created_at
- securities: id, loan_id FK, provider_entity_id FK, type enum(guarantee,land_charge,mortgage,chattel_transfer,pledge,assignment,company_shares,shares,real_estate,vehicle,other), nominal_value decimal(18,2), internal_value decimal(18,2) null, rank, valid_from, valid_until, description, status enum(active,released,expired), timestamps
- guarantees: id, loan_id FK, guarantor_entity_id FK, guarantee_type, max_amount decimal(18,2), valid_from, valid_until, status enum(active,released,expired), timestamps

### Dokumente/Verträge
- documents: id, uuid, original_filename, stored_filename, doc_type string (id_card, passport, contract, amendment, bank_statement, payment_receipt, commercial_register, articles, land_register, guarantee, security, dunning, resolution, shareholder_list, signature_protocol, correspondence, other), category, file_size, mime_type, sha256, document_date, description, tags json, storage_disk, storage_path, version int default 1, status enum(active,archived,deleted), expires_on null, uploaded_by, timestamps, softDeletes
- document_links: id, document_id FK, linkable morph (Entity, Loan, Contract, Payment, Security, ShareTransaction, Resolution, CorporateBodyMember, Investment, IdentityDocument…), timestamps
- contract_templates: id, name, category, description, is_active, timestamps
- contract_template_versions: id, template_id FK, version string, body longtext (HTML mit {{platzhalter}}), placeholders json, created_by, timestamps
- contracts: id, contract_number, loan_id null FK, template_version_id null, title, body_snapshot longtext, status enum(draft,final,signed,cancelled), finalized_at, document_id null (PDF), timestamps
- contract_amendments: id, contract_id FK, amendment_type enum(term_extension,rate_change,repayment_change,deferral,principal_change,security_change,other), description, effective_date, body_snapshot, document_id null, timestamps

### Holding
- shareholders: id, shareholder_number, entity_id FK, joined_on, left_on, status enum(active,inactive), notes, timestamps  (Bestand IMMER berechnet aus share_transactions)
- share_transactions: id, transaction_number, type enum(purchase,sale,transfer,gift,redemption,capital_increase,capital_decrease,correction,other), seller_shareholder_id null, buyer_shareholder_id null, share_count int, price_per_share decimal(18,4) null, total_price decimal(18,2) null, contract_date, economic_transfer_date (wirtschaftlicher Übergang = effective), booking_date, resolution_id null, contract_id null, status enum(draft,review,contract_created,for_signature,signed,resolved,effective,cancelled), note, timestamps  — nur status=effective zählt für Bestand.
- shareholder_list_snapshots: id, document_number, as_of_date, data json, document_id null, sha256, signature_status, created_by, created_at
- investments (Beteiligungen): id, company_entity_id FK, share_percentage decimal(9,6), share_count, acquired_on, acquisition_cost decimal(18,2), current_value decimal(18,2) null, status enum(active,sold,liquidated), notes, timestamps
- corporate_bodies: id, company_entity_id FK, type enum(board,supervisory_board,advisory_board), name, timestamps
- corporate_body_members: id, corporate_body_id FK, person_entity_id FK, role string, is_chair bool, started_on, ended_on null, status enum(active,ended), representation, note, timestamps  — Historie via ended_on, nie löschen.
- resolutions: id, resolution_number, title, company_entity_id FK, type enum(board,supervisory_board,general_meeting,circular,other), applicant_entity_id null, motion text, reasoning text, resolution_text text, resolved_on date null (tatsächliches Beschlussdatum), recorded_at, result enum(accepted,rejected,postponed,withdrawn) null, status enum(draft,submitted,review,voting,accepted,rejected,postponed,withdrawn,for_signature,signed,completed,archived), conflict_of_interest bool, conflict_notes, document_id null, timestamps
- resolution_participants: id, resolution_id FK, entity_id FK, role, attended bool null, timestamps
- resolution_votes: id, resolution_id FK, participant_id FK, vote enum(yes,no,abstain,absent) null, timestamps
- resolution_links: id, resolution_id FK, linkable morph, timestamps
- signature_requests: id, subject morph (Resolution, Contract, ShareholderListSnapshot…), provider string default 'manual', external_id null, status enum(draft,sent,in_progress,completed,declined,expired,error), document_id null (signiertes PDF), created_by, timestamps
- signature_participants: id, signature_request_id FK, entity_id FK, role string, email, status enum(not_sent,sent,opened,signed,declined,expired,error), status_changed_at, timestamps

## Services (app/Services)
- AuditService::log(string $action, ?Model $subject, array $old = [], array $new = [], array $context = [])
- NumberSequenceService::next(string $key, string $pattern) — atomar via settings-Zeile + Transaktion/lockForUpdate
- InterestCalculationService — taggenau, Methoden act_365, act_360, thirty_360, act_act; Staffelzins über loan_interest_terms; BCMath.
- LoanScheduleService — erzeugt/aktualisiert repayment_plan_items aus Vertragsdaten (SOLL) ab effective_from bis Horizont (Vertragsende oder +12 Monate rollierend); markiert vergangene Perioden als origin=assumed solange keine Abweichung erfasst.
- LoanBalanceService — berechnet aus loan_transactions + repayment_plan_items: ausgezahltes Kapital, getilgt, offenes Kapital, Soll-Zinsen, Ist-Zinsen (inkl. assumed getrennt ausgewiesen), offene Zinsen, Gebühren, Gesamtforderung; auch stichtagsfähig (asOf date).
- LoanRecalculationService::recalculate(Loan $loan, string $trigger, ?Carbon $earliestAffected) — löscht/erneuert abgeleitete SOLL-Zeilen ab frühestem Datum, rechnet Zinsen neu (Kapitalverlauf aus Transaktionen), schreibt loan_recalculations-Protokoll. Deterministisch.
- PaymentAllocationService — Verrechnungsreihenfolge aus settings (default: costs, fees, default_interest, interest, principal), erzeugt payment_allocations + loan_transactions.
- DisbursementService — Auszahlungen planen/bestätigen/ausfallen lassen; erzeugt loan_transactions; triggert Recalculation.
- ContractGenerationService — Platzhalter-Ersetzung, Snapshot, PDF via dompdf.
- DocumentStorageService (DocumentStorageInterface: store, retrieve, exists, move, archive, delete, checksum) — Disks: local (storage/app/documents), sftp (league/flysystem-sftp-v3, config aus env). Upload-Pipeline gem. §62 (MIME-Whitelist, Größe, SHA-256, keine ausführbaren Dateien).
- SignatureService (SignatureServiceInterface) — ManualSignatureAdapter (vollständig implementiert: Status manuell pflegen, signiertes PDF hochladen); externe Adapter (DocuSign etc.) als klar gekennzeichnete Stubs mit Interface.
- ShareholdingService — Bestand/Quoten je Aktionär, stichtagsfähig aus share_transactions (nur effective, economic_transfer_date <= Stichtag); Aktionärslisten-PDF + Snapshot.
- ResolutionService — Workflow, Nummern, PDF.
- ReportingService — Reports als Query-Builder + Export PDF/XLSX/CSV (CSV nativ, XLSX via einfachem SpreadsheetML/openspout-frei: wir nutzen CSV + PDF; XLSX über simple XML-Writer App\Support\SimpleXlsxWriter).
- NotificationService — erzeugt DB-Notifications + optional Mail; Scheduler-Command scan:due-items (Fälligkeiten, Abläufe, Wiedervorlagen, Mandate).
- DashboardService — KPI-Berechnung, "Heute relevant".
- BackupService — mysqldump/SQLite-Copy + Doku; Status in settings; Admin-UI.
- SftpStatusService — Verbindungstest (read/write/rename), Status für Admin-UI.

## Enums (app/Enums): LoanStatus, PaymentOrigin, RepaymentItemStatus, RepaymentItemType, DisbursementStatus, InterestMethod, InterestFrequency, RepaymentModel, ShareTransactionType, ShareTransactionStatus, ResolutionType, ResolutionStatus, VoteChoice, SignatureStatus, SecurityType, ReminderStatus/Priority, EntityType, OrganizationRoleType, RelationshipType, FeeType, BookingType, DocumentStatus…
Jedes Enum hat: label(): string (deutsch), severity(): string (danger|warning|success|info|neutral) wo sinnvoll.

## Rollen (Seeder) & Permissions
Rollen: Administrator, Vorstand, Aufsichtsratsvorsitzender, Aufsichtsratsmitglied, Aktionär, Darlehensgeber, Darlehensnehmer, Buchhaltung, Sachbearbeiter, Mitarbeiter, Nur Lesen.
Permissions (Muster `bereich.aktion`): persons.view/create/update/archive; companies.*(dito); loans.view/create/update/approve/archive; payments.view/record/correct/cancel/approve; contracts.view/create/update/finalize/sign; documents.view/upload/download/archive/delete; shares.view/prepare/finalize/list; resolutions.view/create/update/vote/finalize/sign; admin.users/roles/settings/sftp/audit/backups/templates; reports.view; dashboard.view; help.view.
2FA-Pflicht: Administrator, Vorstand, Aufsichtsratsvorsitzender, Aufsichtsratsmitglied (settings-konfigurierbar). Middleware RequireTwoFactor.
Datenscope: Trait/Scope `visibleTo(User)` auf Loan, Document, Resolution etc.: Interne Rollen (Administrator, Vorstand, Buchhaltung, Sachbearbeiter, Mitarbeiter, Nur Lesen) sehen alles; externe (Darlehensgeber/-nehmer, Aktionär, Aufsichtsrat ohne Zusatzrechte) nur Datensätze ihrer assigned entities. Interne Notizen (internal_notes, risk_rating) nur für interne Rollen.

## Routen & Controller (web.php bindet Modul-Dateien ein: routes/modules/*.php)
Namensschema: dashboard, persons.*, companies.*, loans.* (+ loans.schedule.update, loans.recalculate, loans.statement), payments.*, disbursements.*, securities.*, liquidity.index, contracts.*, contract-templates.*, documents.*, holding.dashboard, shareholders.*, share-transactions.*, investments.*, corporate-bodies.*, resolutions.*, signatures.*, calendar.index, reminders.*, reports.* , help.*, faq.*, search.index, admin.users.*, admin.invitations.*, admin.roles.*, admin.settings.*, admin.sftp.*, admin.backups.*, admin.audit.*, admin.status, admin.templates.*, profile.*, two-factor.*.
Auth: eigene Controller (Login mit Rate-Limit + login_attempts, 2FA-Challenge, Passwort-Reset via Laravel, Einladung-Accept-Flow invitations/{token}).

## UI-Bausteine (von Foundation bereitgestellt, ALLE Module nutzen sie)
- layouts/app.blade.php: Sidebar gem. §134, Topbar mit globaler Suche, Kontextwechsler, Benachrichtigungs-Glocke, Privacy-Mode-Toggle, User-Menü. Bootstrap 5.3 (lokal via npm, kein CDN), Bootstrap Icons.
- Komponenten: x-status-badge(severity, icon, label), x-kpi-card(label, value, severity?, hint?), x-money(amount) (respektiert Privacy-Mode), x-origin-badge(origin), x-page-header(title, gold bar), x-help-icon(topic), x-empty-state, x-confirm-form (POST/DELETE mit Bestätigung), Pagination-Styling.
- money(): Blade-Direktive @money($betrag).

## Tests (Pest oder PHPUnit — PHPUnit verwenden)
Feature-/Unit-Tests gem. §139: Zins-Engine (act/365, 30/360, Schaltjahr, Staffelzins), Teilzahlung, ausgefallene Zins-/Tilgungszahlung + Neuberechnung, rückwirkende Erfassung, Stichtag, Aktienlogik (Verkauf, Storno, historischer Stichtag, Prozente), Berechtigungs-Scoping, Upload-Pipeline (lokaler Fake-Disk), Verrechnungsreihenfolge.

## Deployment-Doku: docs/DEPLOYMENT.md (Nginx+PHP-FPM, MariaDB, Cron schedule:run, Queue Worker, HTTPS-Erzwingung, .env-Vorlage), docs/RESTORE.md, README.md.

---

# TEIL 2: Verbindlicher Modul-Vertrag (Stand nach Fundament)

## Bereits vorhanden (NICHT neu anlegen, nur verwenden)
- Alle Models in app/Models (52 Stück, mit Relationen, Casts, visibleTo-Scopes auf Entity/Loan/Document), alle Enums in app/Enums (27, mit label()/severity()).
- App\Support\Money (BCMath), Helpers: format_date(), format_datetime(), format_money(), format_percent(); Blade: @money(...), @date(...).
- App\Services\AuditService::log(string $action, ?Model $subject, array $old=[], array $new=[], array $context=[]).
- App\Services\NumberSequenceService::next(string $prefix, int $digits=5, ?int $year=null) → "DAR-2026-00001".
- Setting::get($group,$key,$default) / Setting::set($group,$key,$value).
- Auth komplett (Login, 2FA, Einladungs-Accept, Passwort-Reset, Profil, Kontextwechsel). Middleware-Aliase: auth, active, two-factor, role, permission.
- Layout resources/views/layouts/app.blade.php (Sidebar §134 fertig, erwartete Routennamen siehe unten) + layouts/guest.
- Komponenten: <x-page-header title label>, <x-status-badge severity icon label>, <x-enum-badge :enum>, <x-origin-badge :origin>, <x-money :amount>, <x-kpi-card label value severity hint icon>, <x-help-icon text>, <x-empty-state icon message>, <x-confirm-form :action method confirm label icon class>.
- Alle Migrationen + Seeder (Rollen/Permissions, MHAG-Initialdaten inkl. Timo Müller 100.000 Aktien, LoanTypes, FAQ, Beispielvorlage). Admin: timo@muellerhv.de.
- routes/web.php lädt automatisch alle routes/modules/*.php innerhalb der auth+active+two-factor-Gruppe.
- Assets lokal: Bootstrap 5.3, Bootstrap Icons, Chart.js unter public/vendor/, CSS public/css/app.css.
- dompdf (barryvdh/laravel-dompdf): Pdf::loadView(...). config/documents.php: disk, max_size_kb, allowed_mime_types, folders.

## Controller-Grundregeln (für ALLE Module)
1. Jede Route mit Berechtigung schützen: `->middleware('permission:loans.view')` bzw. `$this->authorize(...)`/`abort_unless($user->can(...), 403)`.
2. Externe Datenscope: Listen/Detail IMMER über `Model::visibleTo($user)` bzw. äquivalente Prüfung; interne Notizen (internal_notes, risk_rating, notes auf Entity) nur für `$user->isInternal()` rendern.
3. Kritische Aktionen auditieren (AuditService::log, Aktionsnamen `modul.aktion`, z. B. 'loans.created').
4. Geldeingaben: `Money::parse($request->input(...))` akzeptiert "1.234,56"; Validierungsregel numerisch nach Parse. Ausgaben via <x-money> bzw. @money.
5. FormRequests verwenden (app/Http/Requests/<Modul>/...), deutsche Fehlermeldungen via attributes/messages.
6. Keine N+1: with()/withCount() nutzen (preventLazyLoading ist aktiv in dev/test!).
7. UI-Sprache Deutsch, Datum d.m.Y, keine Gedankenstriche in Texten.
8. Pagination: ->paginate(25)->withQueryString(); Filterformulare GET.
9. Nummern über NumberSequenceService (DAR 5-stellig; VER für Verträge 5-stellig; AR/VOR/HV/UB/SB 3-stellig; AL 3-stellig; AB 5-stellig für Aktienbewegungen).
10. Statusübergänge Loan über $loan->transitionStatus(LoanStatus::X, $user, $note).

## Verbindliche Service-Signaturen

### Agent B — app/Services/Loans/ (KEINE Views/Routen/Controller!)
```php
class InterestCalculationService {
    // Zeitraum [from, to): from inklusiv, to exklusiv. Rückgabe Dezimalstring Skala 10.
    public function dayCountFactor(InterestMethod $m, CarbonInterface $from, CarbonInterface $to): string;
    // principal Dezimalstring, ratePercent z. B. '6.000000' (Prozent p.a.). Ungerundet, Skala 10.
    public function interestForPeriod(string $principal, string $ratePercent, InterestMethod $m, CarbonInterface $from, CarbonInterface $to): string;
    // Staffelzins über loan_interest_terms + Kapitalverlauf aus loan_transactions (disbursement/repayment/write_off wirken auf Kapital). Gerundet 2 NK.
    public function interestForLoanPeriod(Loan $loan, CarbonInterface $from, CarbonInterface $to): string;
}
class LoanScheduleService {
    // Erzeugt/aktualisiert SOLL-Zeilen (repayment_plan_items: interest gem. interest_frequency ab effective_from,
    // principal gem. repayment_model, fee aus loan_fees) bis contract_end/due_date, sonst rollierend +12 Monate.
    // Zeilen mit manually_adjusted=true oder erfasstem IST (status nicht planned/assumed) NIE überschreiben/löschen.
    public function generate(Loan $loan): void;
    // Vergangene planned-Zeilen (due_date <= asOf) auf status=assumed, origin=assumed setzen (Grundannahme §24).
    public function rollForwardAssumed(Loan $loan, ?CarbonInterface $asOf = null): void;
}
class LoanBalanceService {
    // Alle Werte Dezimalstrings (2 NK). asOf=null => heute. Stichtagsfähig (§50).
    // Keys: disbursed, repaid, principal_outstanding, interest_charged (SOLL bis asOf),
    // interest_confirmed (IST bestätigt), interest_assumed (nur Annahmen), interest_open,
    // fees_charged, fees_paid, fees_open, default_interest, payments_received,
    // total_receivable, overdue_amount, next_due_date (?string Y-m-d), next_due_amount
    public function balances(Loan $loan, ?CarbonInterface $asOf = null): array;
    // Forderungsaufstellung §51: Zeilen [label, amount, sign] + Summe; Basis für PDF.
    public function statementRows(Loan $loan, CarbonInterface $asOf): array;
}
class LoanRecalculationService {
    // Deterministisch. Ablauf: Snapshot alt (balances) -> Schedule ab earliestAffected neu (generate + rollForwardAssumed)
    // -> Zins-SOLL-Zeilen neu berechnen (Kapitalverlauf!) -> Snapshot neu -> loan_recalculations-Protokoll schreiben.
    public function recalculate(Loan $loan, string $trigger, ?CarbonInterface $earliestAffectedDate = null, ?User $user = null): LoanRecalculation;
}
class PaymentAllocationService {
    // Verrechnung nach Setting('loans','allocation_order'). Erzeugt payment_allocations
    // + loan_transactions (interest_payment/fee_payment/repayment/...; Betrag NEGATIV = Forderung sinkt)
    // und aktualisiert betroffene repayment_plan_items (actual_amount/status/origin).
    public function allocate(Payment $payment, ?array $manualBuckets = null): array; // Rückgabe: bucket => Betrag
}
class DisbursementService {
    public function plan(Loan $loan, array $data, ?User $user = null): LoanDisbursement;
    public function confirm(LoanDisbursement $d, string $actualAmount, CarbonInterface $actualDate, PaymentOrigin $origin, ?User $user = null): void;
    public function markFailed(LoanDisbursement $d, ?string $note = null, ?User $user = null): void; // §32: Ist 0, Folgewerte korrigieren
    public function cancel(LoanDisbursement $d, ?string $reason = null, ?User $user = null): void;
    // Alle: loan_transactions schreiben (disbursement +Betrag) und LoanRecalculationService anstoßen.
}
```

### Agent D — app/Services/Storage/ + app/Services/Contracts*
```php
interface DocumentStorageInterface {   // App\Services\Storage
    public function store(\Illuminate\Http\UploadedFile|string $contents, string $directory, string $originalFilename, array $meta = []): Document; // Pipeline §62: prüfen, UUID, SHA-256, Transfer, Verify, DB
    public function retrieve(Document $document): string;              // Inhalt (für Download-Response)
    public function exists(Document $document): bool;
    public function move(Document $document, string $newDirectory): void;
    public function archive(Document $document): void;
    public function delete(Document $document): void;                  // endgültig; auditieren!
    public function checksum(Document $document): string;              // SHA-256 der gespeicherten Datei
}
class FlysystemDocumentStorage implements DocumentStorageInterface {}  // nutzt config('documents.disk'); AppServiceProvider bindet bereits!
class ContractGenerationService {
    public function render(ContractTemplateVersion $version, array $data): string; // {{platzhalter}} ersetzen; fehlende Platzhalter als Liste zurückmeldbar
    public function missingPlaceholders(ContractTemplateVersion $version, array $data): array;
    public function dataForLoan(Loan $loan): array;  // befüllt Standardplatzhalter aus Darlehen/Entities
    public function generatePdf(Contract $contract): Document; // dompdf, CI-Briefkopf, als Document speichern + verknüpfen
}
class SftpStatusService {
    public function test(): array; // ['online'=>bool,'read'=>bool,'write'=>bool,'rename'=>bool,'error'=>?string,'tested_at'=>string] + Setting('sftp','last_test',...) pflegen
}
```

### Agent E — app/Services/Holding/ + app/Services/Signature/
```php
class ShareholdingService {
    public function totalShares(): int; // Setting('holding','total_shares')
    // Nur Transaktionen status=effective mit economic_transfer_date <= asOf. Rückgabe je Aktionär:
    // ['shareholder'=>Shareholder,'shares'=>int,'percentage'=>string(6NK)] — sortiert nach shares desc. §81 stichtagsfähig.
    public function holdingsAsOf(?CarbonInterface $asOf = null): \Illuminate\Support\Collection;
    public function sharesOf(Shareholder $s, ?CarbonInterface $asOf = null): int;
    public function makeEffective(ShareTransaction $t, ?User $user = null): void;  // Validierung: Verkäufer hat genug Aktien! Kapitalgrenze (total_shares) bei increase beachten. Audit.
    public function cancel(ShareTransaction $t, ?User $user = null): ShareTransaction; // Storno = Gegenbuchung (neue Transaktion reversal_of), NIE löschen (§121)
    public function createListSnapshot(CarbonInterface $asOf, User $user): ShareholderListSnapshot; // Nummer AL-..., Daten-Snapshot als JSON, PDF via dompdf im CI (§82), SHA-256, Document verknüpft
}
interface SignatureServiceInterface {  // App\Services\Signature
    public function create(\Illuminate\Database\Eloquent\Model $subject, Document $pdf, array $participants): SignatureRequest; // participants: [['entity_id'=>..,'role'=>..,'email'=>..],...]
    public function send(SignatureRequest $request): void;
    public function syncStatus(SignatureRequest $request): void;
    public function attachSignedDocument(SignatureRequest $request, Document $signed): void; // Status completed, subject-Status fortschreiben
}
class ManualSignatureAdapter implements SignatureServiceInterface {} // VOLLSTÄNDIG: manuelles Statuspflegen im UI, signiertes PDF hochladen. AppServiceProvider bindet bereits!
class DocuSignAdapter / AdobeSignAdapter — NICHT implementieren; nur je eine Klasse mit Interface, deren Methoden
    eine \RuntimeException('Noch nicht implementiert: Anbindung <Anbieter>. Konfiguration und Implementierung erforderlich.') werfen (§143: Schein-Funktionen verboten, Kennzeichnung Pflicht).
class ResolutionService {
    public function nextNumber(ResolutionType $type): string; // NumberSequenceService, Prefix $type->numberPrefix(), 3-stellig
    public function generatePdf(Resolution $r): Document;     // CI-PDF §97, als Document + document_id
    public function voteSummary(Resolution $r): array;        // ['yes'=>n,'no'=>n,'abstain'=>n,'absent'=>n] — KEINE Mehrheits-/Rechtsbewertung!
}
```

### Agent F — app/Services/ (Dashboard, Notification, Backup)
```php
class DashboardService {
    public function todayRelevant(User $user): array; // Liste ['severity','icon','text','url'] — überfällige Zahlungen, heute fällig, Verträge/Dokumente laufen ab, offene Signaturen, offene Wiedervorlagen, abgelaufene Ausweise (§74)
    public function loanKpis(User $user): array;      // §68-Karten, aggregiert über sichtbare Darlehen via LoanBalanceService
    public function charts(User $user): array;        // Datenreihen für Chart.js (§69)
}
class NotificationService {
    public function notify(User $user, string $message, ?string $url = null, string $severity = 'info'): void; // DB-Notification {message,url,severity}
    public function scanDueItems(): int; // Scheduler: Fälligkeiten (heute/bald/überfällig), Dokument-/Ausweis-/Sicherheiten-Abläufe, Mandatsenden, Wiedervorlagen; erzeugt Notifications + Reminder; idempotent pro Tag
}
class BackupService {
    public function run(): array;   // SQLite: Datei kopieren; MariaDB: mysqldump wenn verfügbar, sonst sauberer Fehler. Nach BACKUP_PATH; Status in Setting('backup','last_run'). Optional Kopie auf Document-Disk exports/backups.
    public function status(): array;
}
```
Artisan-Commands (Agent F): `app:scan-due-items` (täglich, ruft NotificationService::scanDueItems), `app:backup-run` (täglich), Registrierung in routes/console.php mit Schedule::command(...)->dailyAt('05:30') bzw. ->dailyAt('02:00').

## Datei-Eigentum (KEINE fremden Dateien anfassen!)
Gemeinsame Dateien (layout, web.php, Models, Enums, Migrationen, Seeder, CSS) sind TABU — Änderungswünsche als TODO-Kommentar in eigener Datei + im Abschlussbericht melden.

- **Agent A (Stammdaten-UI):** routes/modules/stammdaten.php; app/Http/Controllers/{PersonController, CompanyController, EntitySearchController(GlobalSearch), AddressController, ContactDetailController, BankAccountController, TaxDetailController, IdentityDocumentController, EntityRelationshipController, OrganizationRoleController}.php; app/Http/Requests/MasterData/*; resources/views/persons/*, companies/*, search/*; tests/Feature/MasterData/*
  Routennamen: persons.index/create/store/show/edit/update/archive(POST persons.archive), companies.* (dito), search.index; Unterressourcen als nested POST/PUT/DELETE-Routen (z. B. persons.addresses.store) — Akten mit Tabs §103/104 (Tab-Inhalte via Include, Dokumente-Tab zeigt document_links der Entity, Historie-Tab = AuditLogs zur Entity).
- **Agent B (Darlehens-Engine):** app/Services/Loans/*; tests/Unit/Loans/*, tests/Feature/Loans/Engine*
- **Agent C (Darlehen-UI):** routes/modules/darlehen.php; Controllers {LoanController, LoanScheduleController, PaymentController, DisbursementController, LoanFeeController, LoanInterestTermController, SecurityController, GuaranteeController, DueItemController, LiquidityController, LoanStatementController}; Requests/Loans/*; views loans/*, payments/*, securities/*, liquidity/*, due-items/*; tests/Feature/Loans/Ui*
  Routennamen: loans.index/create/store/show/edit/update/archive, loans.recalculate (POST), loans.statement (GET PDF, Param date), loans.schedule.update (PUT je Item: Ist-Betrag/Status/Kommentar → danach RecalculationService!), loans.interest-terms.store/destroy, loans.fees.*, loans.disbursements.store/confirm/fail/cancel, payments.index/create/store/show/cancel(POST mit Grund, KEIN Löschen), securities.index(+je Loan anlegen: loans.securities.store usw.), guarantees analog, due-items.index (Fälligkeiten §72-nah: Liste kommender/überfälliger repayment_plan_items mit Filtern), liquidity.index (§71: Monat/Quartal/Jahr/12M/frei, aus repayment_plan_items+disbursements), Detailseite §135 mit allen Tabs inkl. Monatsübersicht §28 (Inline-Bearbeitung je Monat), Konto (loan_transactions), Chronik (AuditLogs+StatusHistory), Neuberechnungen (loan_recalculations).
- **Agent D (Dokumente/Verträge):** routes/modules/dokumente.php; app/Services/Storage/*, app/Services/{ContractGenerationService, SftpStatusService}.php; Controllers {DocumentController, ContractTemplateController, ContractController, ContractAmendmentController, Admin/SftpController}; Requests/Documents/*; views documents/*, contracts/*, contract-templates/*, admin/sftp/*; tests/Feature/Documents/*
  Routennamen: documents.index/create/store/show/download/archive/destroy, documents.link (POST: an beliebiges Model via morph), contracts.index/create/store/show/finalize/pdf, contracts.amendments.store, contract-templates.index/create/store/show/edit/update + contract-templates.versions.store, admin.sftp.index/test.
  PDF-CI-Layout: gemeinsames Blade views/pdf/layout.blade.php MIT Logo (public/images/logo-mhag.jpg), Fußband-Pflichtangaben (Footer 2 Zeilen aus BAUPLAN Teil 1), Goldbalken — DIESES Layout gehört Agent D, Agenten C/E binden es ein (@extends('pdf.layout')). Agent D baut es ZUERST.
- **Agent E (Holding):** routes/modules/holding.php; app/Services/Holding/*, app/Services/Signature/*; Controllers {HoldingDashboardController, ShareholderController, ShareTransactionController, ShareholderListController, InvestmentController, CorporateBodyController, ResolutionController, ResolutionVoteController, SignatureRequestController}; Requests/Holding/*; views holding/*, shareholders/*, share-transactions/*, investments/*, corporate-bodies/*, resolutions/*, signatures/*; tests/Feature/Holding/*
  Routennamen: holding.dashboard (§106 KPIs+Widgets), shareholders.index/show/store/update, share-transactions.index/create/store/show/make-effective(POST)/cancel(POST), shareholders.list.create (POST → Snapshot+PDF), shareholders.list.download/{snapshot}, investments.*, corporate-bodies.index/show + corporate-bodies.members.store/end (Historie §87: Mitglieder nie löschen, ended_on setzen; Stichtagsabfrage ?as_of=), resolutions.index/create/store/show/edit/update/vote(POST)/finalize(POST)/pdf, resolutions register mit Filtern §98 + PDF-Export, signatures.index/create/store/show/send/mark(POST participant status)/attach-signed(POST upload via DocumentStorage).
- **Agent F (Controlling/Organisation/Admin):** routes/modules/{organisation.php, admin.php}; app/Services/{DashboardService, NotificationService, BackupService}.php; app/Console/Commands/*; Controllers {DashboardController, CalendarController, ReminderController, NotificationController, ReportController, HelpController, FaqAdminController(unter Admin), Admin/{UserController, InvitationController, RoleController, SettingController, AuditLogController, BackupController, SystemStatusController}}; views dashboard/*, calendar/*, reminders/*, notifications/*, reports/*, help/*, admin/{users,invitations,roles,settings,audit,backups,status}/*; Mail: app/Mail/UserInvitationMail.php + views/mail/invitation.blade.php; tests/Feature/Organisation/*
  Routennamen: dashboard, calendar.index (Monatsansicht, Quellen §72: repayment_plan_items, Verträge/contract_end, Kündigungsfristen, securities/guarantees valid_until, identity_documents expires_on, corporate_body_members ended_on, reminders), reminders.index/create/store/update/done(POST)/cancel(POST), notifications.index + notifications.read-all (POST), reports.index + reports.show/{key} + Export ?format=pdf|xlsx|csv (CSV nativ; XLSX als einfaches SpreadsheetML-XML; PDF dompdf) — Reports gem. §107-Liste soweit Daten vorhanden, help.index/page/{slug}/search + faq, changelog ("Was ist neu?"), admin.users.index/create/store/show/edit/update/deactivate(POST)/activate(POST), admin.invitations.index/store/resend(POST)/revoke(POST) (Token: Str::random(64), sha256-Hash speichern, Mail mit Link route('invitations.show',$token) — Mail::to()->queue optional, log-Mailer ok), admin.roles.index/create/store/edit/update (Permissions-Checkboxen; Systemrollen nicht löschbar), admin.settings.index/update (Gruppen: security.two_factor_required_roles, loans.allocation_order, documents.*), admin.audit.index (Filter Benutzer/Aktion/Zeitraum/Objekt), admin.backups.index/run(POST), admin.status (Systemstatus §136: PHP/Laravel-Version, DB ok, Queue-Jobs offen (jobs-Tabelle), letzte Backups, SFTP-Status aus Setting, fehlgeschlagene Logins 24h, letzte Recalculation-Fehler), faq-Verwaltung admin.faq.* (CRUD, Sichtbarkeit).
  Dashboard §68/74: KPI-Karten via DashboardService, "Heute relevant"-Block, Charts (Chart.js lokal: asset('vendor/chartjs/chart.umd.min.js')).

## Test-Konventionen
- PHPUnit, RefreshDatabase, SQLite :memory: (phpunit.xml fertig).
- Seeder in Tests: `$this->seed(\Database\Seeders\RolePermissionSeeder::class);` (+ InitialDataSeeder wo nötig).
- User-Fabrik: User::factory() existiert (Standard-Laravel). Rollen via `$user->assignRole('Administrator')` nach Seeding.
- Fake-Storage: `Storage::fake('documents');` config('documents.disk') bleibt 'documents'.
- Jeder Agent liefert lauffähige Tests für sein Modul; `php artisan test --filter=<Modul>` muss grün sein.
