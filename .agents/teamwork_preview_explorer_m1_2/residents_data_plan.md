# Residents & Households Data Specification for DemoDataSeeder

**Target File:** `database/seeders/DemoDataSeeder.php`  
**Milestone:** M1 (Demo Dataset Seeder)  
**Agent:** `teamwork_preview_explorer_m1_2` (Residents & Households Data Plan Explorer)  
**Parent Agent:** `1a0f86b1-f133-4d9d-81e5-8f9757cf60a2`  
**Date:** September 13, 2026  
**Status:** Complete & Ready for Implementation  

---

## Executive Summary

This document specifies the exhaustive, production-grade dataset for **Households** and **Residents** in Barangay Sta. Felomina, Makilala, Cotabato, to be implemented in `DemoDataSeeder.php`.

The specification provides:
1. **28 Households**: Exactly 4 households per Purok across all 7 Puroks (Purok 1 through Purok 7), with deterministic codes (`HH-P1-001` through `HH-P7-004`), authentic street and sitio addresses, income brackets mapped into `notes`, and foreign keys mapped to corresponding Purok Chairpersons.
2. **84 Residents**: Exactly 12 residents per Purok (2 to 4 residents per household), with deterministic codes (`RES-P1-001` through `RES-P7-012`), authentic Filipino naming conventions, realistic age distributions, 50/50 sex parity, full civil status representation, realistic occupations, and strict voter eligibility logic (all minors under 18 non-voters; 95% adults registered).
3. **All 4 ResidentStatus Enum Cases**:
   - `active`: 74 residents (88.1%)
   - `transferred`: 4 residents (4.8%) — relocated to Davao City, Digos, Kidapawan, and GenSan
   - `deceased`: 3 residents (3.6%) — elderly residents with death remarks
   - `inactive`: 3 residents (3.6%) — OFWs in Saudi Arabia, Dubai, and international sea vessel
4. **Household Relationships & Head Matching**: Every household designates exactly one resident head where `is_head_of_household = true`. The household record's `household_head_name` matches verbatim the full name of that resident. Relationships (`Head`, `Spouse`, `Child`, `Parent`, `Relative`) are documented in `remarks`.
5. **100% Idempotent Execution**: Drop-in PHP implementation pattern using `Household::updateOrCreate()` and `Resident::updateOrCreate()` keyed on deterministic unique codes, with companion `AuditLog` creation.

---

## 1. Schema & Attribute Mapping Reference

### 1.1 `households` Table Mapping

| Schema Column | Type | Nullable | Source Field / Strategy | Description |
|---|---|---|---|---|
| `id` | bigint (PK) | No | Auto-increment | Database primary key |
| `household_code` | varchar(255) | No | `'household_code'` | Deterministic format: `HH-P{purok_number}-{sequence:03d}` (e.g. `HH-P1-001`) |
| `purok_id` | bigint (FK) | No | `Purok::where('code', $purokCode)->id` | Constrained foreign key to `puroks.id` (1 to 7) |
| `address` | varchar(255) | No | `'address'` | Street / Sitio address in Sta. Felomina |
| `household_head_name` | varchar(255) | No | Full name of designated head | Matches designated resident where `is_head_of_household === true` |
| `notes` | text | Yes | Income bracket & dependents | e.g. `Monthly Income: ₱22,000 (Low-Middle Income); 3 members.` |
| `created_by_id` | bigint (FK) | Yes | Purok Chair `users.id` | Matched to assigned Purok Chairperson (`purok1`..`purok7@stafelomina.local`) |
| `updated_by_id` | bigint (FK) | Yes | `null` | Nullable |
| `created_at`, `updated_at` | timestamps | No | `now()` / preserved | Standard Eloquent timestamps |

### 1.2 `residents` Table Mapping

| Schema Column | Type | Nullable | Source Field / Strategy | Description |
|---|---|---|---|---|
| `id` | bigint (PK) | No | Auto-increment | Database primary key |
| `resident_code` | varchar(255) | No | `'resident_code'` | Deterministic format: `RES-P{purok_number}-{sequence:03d}` (e.g. `RES-P1-001`) |
| `first_name` | varchar(255) | No | `'first_name'` | Filipino first name (e.g. `Juanito`, `Elena`) |
| `middle_name` | varchar(255) | Yes | `'middle_name'` | Maternal surname (e.g. `Mendoza`, `Garcia`) |
| `last_name` | varchar(255) | No | `'last_name'` | Paternal surname (e.g. `Dela Cruz`, `Santos`) |
| `suffix` | varchar(255) | Yes | `'suffix'` | e.g. `Jr.`, `Sr.`, `III`, or `null` |
| `normalized_full_name` | varchar(255) | No | Auto-computed | Computed in model `saving` hook: `Str::squish()->lower()` |
| `sex` | varchar(255) | No | `'sex'` | `'male'` or `'female'` |
| `birthdate` | date | No | `'birthdate'` | `YYYY-MM-DD` (realistic age between 11 and 76 years) |
| `civil_status` | varchar(255) | No | `'civil_status'` | `'single'`, `'married'`, `'widowed'`, or `'separated'` |
| `contact_number` | varchar(255) | Yes | `'contact_number'` | Mobile number `09xx-xxx-xxxx`, international for OFWs, `null` for minors |
| `address` | varchar(255) | No | `'address'` | Mirrors household address |
| `purok_id` | bigint (FK) | No | `Purok::where('code', $purokCode)->id` | Assigned Purok ID |
| `household_id` | bigint (FK) | No | `Household::where('household_code', ...)->id` | Parent Household ID |
| `is_registered_voter` | boolean | No | `'is_registered_voter'` | Maps from prompt's `is_voter`. Minors (<18) = `false`; Adults = realistic flag |
| `status` | varchar(255) | No | `'status'` | Enum `ResidentStatus`: `'active'`, `'transferred'`, `'deceased'`, `'inactive'` |
| `birthplace` | varchar(255) | Yes | `'birthplace'` | Usually `Makilala, Cotabato` or neighboring municipalities |
| `occupation` | varchar(255) | Yes | `'occupation'` | Real local occupation (Farmer, Teacher, BHW, Driver, Student, etc.) |
| `remarks` | text | Yes | `'remarks'` | Household relationship (`Head`, `Spouse`, `Child`, `Parent`), OFW notes, etc. |
| `created_by_id` | bigint (FK) | Yes | Purok Chair `users.id` | Assigned Purok Chairperson ID |

---

## 2. 28 Households Master Specification

| Purok | Household Code | Household Head Name | Street Address (Sta. Felomina) | Income Range / Notes | Members |
|---|---|---|---|---|---|
| **Purok 1** | `HH-P1-001` | Juanito Mendoza Dela Cruz | 104 Magsaysay St., Sitio Centro | Monthly Income: ₱22,000 (Low-Middle Income); 3 members | 3 |
| **Purok 1** | `HH-P1-002` | Roberto Gomez Santos | 212 Rizal Street, Sitio Centro | Monthly Income: ₱16,000 (Low Income); 4 members | 4 |
| **Purok 1** | `HH-P1-003` | Elena Villafuerte Alcantara | 045 Quezon Boulevard, Sitio Centro | Monthly Income: ₱14,000 (Low Income); 3 members | 3 |
| **Purok 1** | `HH-P1-004` | Danilo Salcedo Morales | 088 Mabini Extension, Sitio Centro | Monthly Income: ₱32,000 (Middle Income); 2 members | 2 |
| **Purok 2** | `HH-P2-001` | Manuel Castro Bautista | 115 Malipayon Street, Sitio Malipayon | Monthly Income: ₱19,000 (Low Income); 4 members | 4 |
| **Purok 2** | `HH-P2-002` | Cristina Velasco Reyes | 072 Acacia Lane, Sitio Malipayon | Monthly Income: ₱35,000 (Middle Income); 3 members | 3 |
| **Purok 2** | `HH-P2-003` | Francisco Beltran Dimaculangan | 034 Narra Drive, Sitio Malipayon | Monthly Income: ₱15,000 (Low Income); 3 members | 3 |
| **Purok 2** | `HH-P2-004` | Rodrigo Villanueva Magbanua | 098 Maharlika Road, Sitio Malipayon | Monthly Income: ₱18,000 (Low Income); 2 members | 2 |
| **Purok 3** | `HH-P3-001` | Antonio Salazar Flores | 018 National Highway Km 114 | Monthly Income: ₱25,000 (Middle Income); 3 members | 3 |
| **Purok 3** | `HH-P3-002` | Ramon Mercado Catapang | 056 Highway Bypass Road | Monthly Income: ₱13,500 (Low Income); 3 members | 3 |
| **Purok 3** | `HH-P3-003` | Ernesto Gomez Navarro | 142 Terminal Road | Monthly Income: ₱20,000 (Low-Middle Income); 4 members | 4 |
| **Purok 3** | `HH-P3-004` | Marites Soriano Villafuerte | 089 Junction Commercial Strip | Monthly Income: ₱28,000 (Middle Income); 2 members | 2 |
| **Purok 4** | `HH-P4-001` | Jaime Fernandez Perez | 023 Riverside Bank Road | Monthly Income: ₱17,000 (Low Income); 4 members | 4 |
| **Purok 4** | `HH-P4-002` | Rogelio Mendoza Tolentino | 077 Old River Crossing | Monthly Income: ₱9,000 (Indigent / Pensioner); 2 members | 2 |
| **Purok 4** | `HH-P4-003` | Andres Salazar Macaraeg | 105 Riverview Pathway | Monthly Income: ₱14,500 (Low Income); 3 members | 3 |
| **Purok 4** | `HH-P4-004` | Leonora Bautista Salvador | 048 Fishery Lane | Monthly Income: ₱16,000 (Low Income); 3 members | 3 |
| **Purok 5** | `HH-P5-001` | Fernando Gomez Custodio | 031 Maharlika Central | Monthly Income: ₱21,000 (Low-Middle Income); 3 members | 3 |
| **Purok 5** | `HH-P5-002` | Divina Mercado Gonzales | 064 Sitio Maharlika Upper | Monthly Income: ₱55,000 (High Income - OFW); 4 members | 4 |
| **Purok 5** | `HH-P5-003` | Marivic Soriano Ramos | 092 Maharlika Valley | Monthly Income: ₱13,000 (Low Income); 2 members | 2 |
| **Purok 5** | `HH-P5-004` | Jaime Tolentino Aquino | 118 Maharlika Feeder Road | Monthly Income: ₱18,500 (Low Income); 3 members | 3 |
| **Purok 6** | `HH-P6-001` | Remedios Villafuerte Mendoza | 014 Upland Ridge, Sitio San Isidro | Monthly Income: ₱10,500 (Indigent / Pensioner); 2 members | 2 |
| **Purok 6** | `HH-P6-002` | Carmela Morales Garcia | 042 San Isidro Trail, Sitio San Isidro | Monthly Income: ₱24,000 (Low-Middle Income); 4 members | 4 |
| **Purok 6** | `HH-P6-003` | Jocelyn Catapang Soriano | 068 Mountain View Road, Sitio San Isidro | Monthly Income: ₱26,000 (Middle Income); 3 members | 3 |
| **Purok 6** | `HH-P6-004` | Rosalie Alcantara Valenzuela | 095 Forest Reserve Road, Sitio San Isidro | Monthly Income: ₱30,000 (Middle Income); 3 members | 3 |
| **Purok 7** | `HH-P7-001` | Rowena Perez Hernandez | 028 Pag-asa Access Road, Sitio Pag-asa | Monthly Income: ₱23,000 (Low-Middle Income); 3 members | 3 |
| **Purok 7** | `HH-P7-002` | Angelica Navarro Pascual | 055 Sitio Pag-asa Proper, Sitio Pag-asa | Monthly Income: ₱15,500 (Low Income); 3 members | 3 |
| **Purok 7** | `HH-P7-003` | Andres Ramos Del Rosario | 083 Boundary Marker Street, Sitio Pag-asa | Monthly Income: ₱27,000 (Middle Income); 4 members | 4 |
| **Purok 7** | `HH-P7-004` | Teresa Bautista Castillo | 112 Sitio Pag-asa Edge, Sitio Pag-asa | Monthly Income: ₱42,000 (Middle-High Income - OFW); 2 members | 2 |

---

## 3. 84 Residents Master Specification

### 3.1 Distribution Summary

- **Total Residents:** 84
- **Sex Breakdown:** 42 Male (50.0%), 42 Female (50.0%)
- **Status Breakdown:**
  - `active`: 74 (88.1%)
  - `transferred`: 4 (4.8%)
  - `deceased`: 3 (3.6%)
  - `inactive`: 3 (3.6%)
- **Civil Status Breakdown:**
  - `married`: 46 (54.8%)
  - `single`: 29 (34.5%)
  - `widowed`: 6 (7.1%)
  - `separated`: 3 (3.6%)
- **Voter Registration:**
  - Minors (Age < 18): 9 (100% Non-Voters: `is_registered_voter = false`)
  - Adults (Age >= 18): 75 (71 Registered Voters: 94.7%, 4 Unregistered: 5.3%)

---

### 3.2 Master Resident Records Table

#### Purok 1 (Sitio Centro — 12 Residents)

| Code | Full Name | Sex | Birthdate (Age) | Civil Status | Contact No. | Voter | Status | Occupation | Rel / Remarks |
|---|---|---|---|---|---|---|---|---|---|
| `RES-P1-001` | Juanito Mendoza Dela Cruz | male | 1978-05-14 (48) | married | 0917-234-1101 | Yes | active | Farmer & Shopkeeper | Head; Head of Household |
| `RES-P1-002` | Teresa Garcia Dela Cruz | female | 1982-11-20 (43) | married | 0917-234-1102 | Yes | active | Sari-Sari Store Manager | Spouse |
| `RES-P1-003` | Christian Garcia Dela Cruz | male | 2006-03-15 (20) | single | 0917-234-1103 | Yes | active | College Student | Child |
| `RES-P1-004` | Roberto Gomez Santos | male | 1980-08-25 (46) | married | 0920-345-2201 | Yes | active | Tricycle Driver | Head; Head of Household |
| `RES-P1-005` | Marivic Ramos Santos | female | 1984-02-14 (42) | married | 0920-345-2202 | Yes | active | Homemaker / Dressmaker | Spouse |
| `RES-P1-006` | Kevin Ramos Santos | male | 2008-01-10 (18) | single | 0920-345-2203 | Yes | active | Senior High Student | Child |
| `RES-P1-007` | Bea Ramos Santos | female | 2012-07-22 (14) | single | *null* | No | active | Elementary Student | Child (Minor) |
| `RES-P1-008` | Elena Villafuerte Alcantara | female | 1960-04-18 (66) | widowed | 0928-456-3301 | Yes | active | Store Owner | Head; Senior Citizen |
| `RES-P1-009` | Antonio Perez Alcantara | male | 1957-10-12 (—) | married | *null* | No | deceased | Former Barangay Tanod | Spouse; Deceased (2024) |
| `RES-P1-010` | Grace Villafuerte Alcantara | female | 1992-09-05 (34) | single | 0928-456-3302 | Yes | active | Daycare Volunteer | Child; Caretaker |
| `RES-P1-011` | Danilo Salcedo Morales | male | 1958-12-03 (67) | married | 0939-567-4401 | Yes | active | Retired Public Teacher | Head; Senior Citizen |
| `RES-P1-012` | Jason Aquino Morales | male | 1993-06-28 (33) | single | 0939-567-4402 | No | inactive | Merchant Seaman | Child; Deployed on cargo ship |

#### Purok 2 (Sitio Malipayon — 12 Residents)

| Code | Full Name | Sex | Birthdate (Age) | Civil Status | Contact No. | Voter | Status | Occupation | Rel / Remarks |
|---|---|---|---|---|---|---|---|---|---|
| `RES-P2-001` | Manuel Castro Bautista | male | 1976-03-22 (50) | married | 0918-123-5501 | Yes | active | Master Carpenter | Head; Head of Household |
| `RES-P2-002` | Remedios Santos Bautista | female | 1979-09-14 (47) | married | 0918-123-5502 | Yes | active | Food Vendor | Spouse |
| `RES-P2-003` | Mark Lester Santos Bautista | male | 2004-11-08 (21) | single | 0918-123-5503 | Yes | active | Welder Apprentice | Child |
| `RES-P2-004` | Princess Joy Santos Bautista | female | 2010-05-30 (16) | single | *null* | No | active | High School Student | Child (Minor) |
| `RES-P2-005` | Cristina Velasco Reyes | female | 1985-06-18 (41) | married | 0922-234-6601 | Yes | active | Elementary Teacher | Head; Head of Household |
| `RES-P2-006` | Eduardo Tan Reyes | male | 1983-01-27 (43) | married | 0922-234-6602 | Yes | active | Motorcycle Mechanic | Spouse |
| `RES-P2-007` | Ethan John Velasco Reyes | male | 2014-04-11 (12) | single | *null* | No | active | Elementary Student | Child (Minor) |
| `RES-P2-008` | Francisco Beltran Dimaculangan | male | 1974-11-05 (51) | married | 0930-345-7701 | Yes | active | Rubber Farmer & Tapper | Head; Head of Household |
| `RES-P2-009` | Luzviminda Ramos Dimaculangan | female | 1978-08-19 (48) | married | 0930-345-7702 | Yes | active | Agricultural Caretaker | Spouse |
| `RES-P2-010` | Gerald Ramos Dimaculangan | male | 2005-12-02 (20) | single | 0930-345-7703 | No | active | Farm Laborer | Child; Unregistered adult |
| `RES-P2-011` | Rodrigo Villanueva Magbanua | male | 1977-07-09 (49) | separated | 0947-456-8801 | Yes | active | Private Security Guard | Head; Separated |
| `RES-P2-012` | Angelica Cruz Magbanua | female | 2003-02-24 (23) | single | 0947-456-8802 | Yes | transferred | Call Center Agent | Child; Transferred to Kidapawan |

#### Purok 3 (National Highway Junction — 12 Residents)

| Code | Full Name | Sex | Birthdate (Age) | Civil Status | Contact No. | Voter | Status | Occupation | Rel / Remarks |
|---|---|---|---|---|---|---|---|---|---|
| `RES-P3-001` | Antonio Salazar Flores | male | 1975-05-30 (51) | married | 0919-567-9901 | Yes | active | Auto Mechanic | Head; Head of Household |
| `RES-P3-002` | Clarita David Flores | female | 1980-10-14 (45) | married | 0919-567-9902 | Yes | active | Auto Parts Cashier | Spouse |
| `RES-P3-003` | Antonette David Flores | female | 2007-08-20 (19) | single | 0919-567-9903 | Yes | active | IT College Student | Child |
| `RES-P3-004` | Ramon Mercado Catapang | male | 1970-12-16 (55) | married | 0921-678-0011 | Yes | active | Chief Barangay Tanod | Head; Tanod Deputy |
| `RES-P3-005` | Teresita Ocampo Catapang | female | 1973-04-02 (53) | married | 0921-678-0012 | Yes | active | Community Tailor | Spouse |
| `RES-P3-006` | Raymond Ocampo Catapang | male | 2000-09-18 (25) | single | 0921-678-0013 | Yes | active | Delivery Driver | Child |
| `RES-P3-007` | Ernesto Gomez Navarro | male | 1969-02-28 (57) | married | 0932-789-1122 | Yes | active | Vulcanizing Operator | Head; Head of Household |
| `RES-P3-008` | Rosanna Cruz Navarro | female | 1972-11-11 (53) | married | 0932-789-1123 | Yes | active | Eatery Cook | Spouse |
| `RES-P3-009` | Joshua Cruz Navarro | male | 2002-06-04 (24) | single | 0932-789-1124 | Yes | active | Auto Electrician | Child |
| `RES-P3-010` | Jasmine Cruz Navarro | female | 2011-01-25 (15) | single | *null* | No | active | High School Student | Child (Minor) |
| `RES-P3-011` | Marites Soriano Villafuerte | female | 1981-08-07 (45) | separated | 0945-890-2233 | Yes | active | Bakery Owner | Head; Separated |
| `RES-P3-012` | Dominic Villafuerte Jr. | male | 2001-04-19 (25) | single | 0945-890-2234 | Yes | transferred | Commercial Sales Agent | Child; Transferred to Davao City |

#### Purok 4 (Riverside Zone — 12 Residents)

| Code | Full Name | Sex | Birthdate (Age) | Civil Status | Contact No. | Voter | Status | Occupation | Rel / Remarks |
|---|---|---|---|---|---|---|---|---|---|
| `RES-P4-001` | Jaime Fernandez Perez | male | 1973-10-10 (52) | married | 0915-901-3344 | Yes | active | Rice & Veg Farmer | Head; Head of Household |
| `RES-P4-002` | Anita Morales Perez | female | 1976-12-05 (49) | married | 0915-901-3345 | Yes | active | Veg Wholesaler | Spouse |
| `RES-P4-003` | Darwin Morales Perez | male | 2003-08-14 (23) | single | 0915-901-3346 | Yes | active | Farm Machinery Op | Child |
| `RES-P4-004` | Jenny Morales Perez | female | 2009-03-29 (17) | single | *null* | No | active | High School Student | Child (Minor) |
| `RES-P4-005` | Rogelio Mendoza Tolentino | male | 1948-03-12 (—) | married | *null* | No | deceased | Retired Land Tiller | Head; Deceased (Feb 2026) |
| `RES-P4-006` | Natividad Santos Tolentino | female | 1952-09-21 (73) | widowed | 0926-012-4455 | Yes | active | Social Pensioner | Surviving Spouse; Senior |
| `RES-P4-007` | Andres Salazar Macaraeg | male | 1982-04-03 (44) | married | 0935-123-5566 | Yes | active | Rubber Tapper | Head; Head of Household |
| `RES-P4-008` | Gemma Flores Macaraeg | female | 1986-07-28 (40) | married | 0935-123-5567 | Yes | active | Fruit Vendor | Spouse |
| `RES-P4-009` | Angelo Flores Macaraeg | male | 2013-10-15 (12) | single | *null* | No | active | Elementary Student | Child (Minor) |
| `RES-P4-010` | Leonora Bautista Salvador | female | 1978-01-17 (48) | married | 0948-234-6677 | Yes | active | Barangay Health Worker | Head; BHW Volunteer |
| `RES-P4-011` | Vicente Ramos Salvador | male | 1975-06-23 (51) | married | 0948-234-6678 | Yes | active | Poultry Caretaker | Spouse |
| `RES-P4-012` | Karen Joy Ramos Salvador | female | 2005-02-14 (21) | single | 0948-234-6679 | Yes | active | Nursing Student | Child |

#### Purok 5 (Sitio Maharlika — 12 Residents)

| Code | Full Name | Sex | Birthdate (Age) | Civil Status | Contact No. | Voter | Status | Occupation | Rel / Remarks |
|---|---|---|---|---|---|---|---|---|---|
| `RES-P5-001` | Fernando Gomez Custodio | male | 1979-11-19 (46) | married | 0917-345-7788 | Yes | active | Welder & Fabricator | Head; Head of Household |
| `RES-P5-002` | Cynthia Diaz Custodio | female | 1982-05-24 (44) | married | 0917-345-7789 | Yes | active | Homemaker | Spouse |
| `RES-P5-003` | Bryan Diaz Custodio | male | 2006-08-30 (20) | single | 0917-345-7790 | No | active | TechVoc Trainee | Child; Unregistered adult |
| `RES-P5-004` | Divina Mercado Gonzales | female | 1983-09-08 (43) | married | +966-50-123-4567 | Yes | inactive | OFW Nurse (Riyadh) | Head; OFW in Saudi Arabia |
| `RES-P5-005` | Nestor Alcantara Gonzales | male | 1980-03-14 (46) | married | 0927-456-8899 | Yes | active | Van Driver & Caretaker | Spouse; Local caretaker |
| `RES-P5-006` | Nicole Mercado Gonzales | female | 2008-04-12 (18) | single | 0927-456-8900 | Yes | active | Grade 12 Student | Child; First-time voter |
| `RES-P5-007` | Nathan Mercado Gonzales | male | 2015-06-25 (11) | single | *null* | No | active | Elementary Student | Child (Minor) |
| `RES-P5-008` | Marivic Soriano Ramos | female | 1971-07-22 (55) | widowed | 0938-567-9900 | Yes | active | Master Tailor | Head; Solo Parent |
| `RES-P5-009` | Paolo Soriano Ramos | male | 2001-12-10 (24) | single | 0938-567-9901 | Yes | active | Agri Supply Clerk | Child |
| `RES-P5-010` | Jaime Tolentino Aquino | male | 1968-01-30 (58) | married | 0949-678-0012 | Yes | active | Corn & Banana Grower | Head; Head of Household |
| `RES-P5-011` | Corazon Beltran Aquino | female | 1972-04-16 (54) | married | 0949-678-0013 | Yes | active | Produce Trader | Spouse |
| `RES-P5-012` | Mark Anthony Aquino | male | 1999-10-05 (26) | single | 0949-678-0014 | Yes | active | Heavy Equipment Op | Child |

#### Purok 6 (Upper Hills / Sitio San Isidro — 12 Residents)

| Code | Full Name | Sex | Birthdate (Age) | Civil Status | Contact No. | Voter | Status | Occupation | Rel / Remarks |
|---|---|---|---|---|---|---|---|---|---|
| `RES-P6-001` | Remedios Villafuerte Mendoza | female | 1950-02-15 (76) | widowed | 0916-789-1133 | Yes | active | Backyard Gardener | Head; Senior Citizen / PWD |
| `RES-P6-002` | Felipe Castro Mendoza | male | 1946-08-20 (—) | widowed | *null* | No | deceased | Retired Farmer | Brother; Deceased (late 2025) |
| `RES-P6-003` | Carmela Morales Garcia | female | 1974-10-09 (51) | married | 0928-890-2244 | Yes | active | Rubber Planter | Head; Head of Household |
| `RES-P6-004` | Rolando Dela Cruz Garcia | male | 1971-05-18 (55) | married | 0928-890-2245 | Yes | active | Plantation Overseer | Spouse |
| `RES-P6-005` | Rona Morales Garcia | female | 1998-03-25 (28) | single | 0928-890-2246 | Yes | active | Pharmacy Assistant | Child |
| `RES-P6-006` | Rochelle Morales Garcia | female | 2000-11-14 (25) | married | 0928-890-2247 | Yes | transferred | Admin Clerk | Child; Transferred to Digos |
| `RES-P6-007` | Jocelyn Catapang Soriano | female | 1980-08-11 (46) | married | 0939-901-3355 | Yes | active | Fruit Grower (Durian) | Head; Head of Household |
| `RES-P6-008` | Noel Dimaculangan Soriano | male | 1977-01-22 (49) | married | 0939-901-3356 | Yes | active | Orchard Supervisor | Spouse |
| `RES-P6-009` | Justin Catapang Soriano | male | 2011-09-03 (15) | single | *null* | No | active | High School Student | Child (Minor) |
| `RES-P6-010` | Rosalie Alcantara Valenzuela | female | 1982-12-07 (43) | married | 0946-012-4466 | Yes | active | Rural Health Midwife | Head; Head of Household |
| `RES-P6-011` | Gregorio Santos Valenzuela | male | 1979-04-15 (47) | married | 0946-012-4467 | Yes | active | Coffee Farm Manager | Spouse |
| `RES-P6-012` | Christian Santos Valenzuela | male | 2007-07-29 (19) | single | 0946-012-4468 | Yes | active | Criminology Student | Child |

#### Purok 7 (Sitio Pag-asa — 12 Residents)

| Code | Full Name | Sex | Birthdate (Age) | Civil Status | Contact No. | Voter | Status | Occupation | Rel / Remarks |
|---|---|---|---|---|---|---|---|---|---|
| `RES-P7-001` | Rowena Perez Hernandez | female | 1977-03-16 (49) | married | 0918-234-5577 | Yes | active | Women's Coordinator | Head; Community Leader |
| `RES-P7-002` | Alberto Flores Hernandez | male | 1975-08-20 (51) | married | 0918-234-5578 | Yes | active | Freight Truck Driver | Spouse |
| `RES-P7-003` | Sheila Perez Hernandez | female | 2004-06-12 (22) | single | 0918-234-5579 | Yes | active | Bookkeeper | Child |
| `RES-P7-004` | Angelica Navarro Pascual | female | 1984-11-23 (41) | married | 0929-345-6688 | Yes | active | Feed Store Dealer | Head; Head of Household |
| `RES-P7-005` | Cesar Gomez Pascual | male | 1981-02-18 (45) | married | 0929-345-6689 | Yes | active | Livestock Raiser | Spouse |
| `RES-P7-006` | Carlito Navarro Pascual | male | 2012-10-08 (13) | single | *null* | No | active | Elementary Student | Child (Minor) |
| `RES-P7-007` | Andres Ramos Del Rosario | male | 1973-09-04 (53) | married | 0933-456-7799 | Yes | active | Construction Foreman | Head; Head of Household |
| `RES-P7-008` | Maria Fe Santos Del Rosario | female | 1976-05-17 (50) | married | 0933-456-7800 | Yes | active | Tailoring Contractor | Spouse |
| `RES-P7-009` | Francis Santos Del Rosario | male | 2002-01-29 (24) | single | 0933-456-7801 | Yes | active | Site Electrician | Child |
| `RES-P7-010` | Patricia Del Rosario | female | 2005-07-14 (21) | single | 0933-456-7802 | Yes | transferred | University Student | Child; Transferred to GenSan |
| `RES-P7-011` | Teresa Bautista Castillo | female | 1963-06-30 (63) | widowed | 0947-567-8811 | Yes | active | Retired Seamstress | Head; Senior Citizen |
| `RES-P7-012` | Jeffrey Bautista Castillo | male | 1991-12-14 (34) | single | +971-50-987-6543 | Yes | inactive | HVAC Tech (Dubai) | Child; OFW in Dubai, UAE |

---

## 4. Drop-in PHP Data Array Specification

Below are the exact PHP data arrays formatted for direct incorporation into `database/seeders/DemoDataSeeder.php`.

```php
<?php

namespace Database\Seeders;

use App\Enums\ResidentStatus;

class DemoDataSeeder extends Seeder
{
    /**
     * Complete 28-Household Dataset Specification
     *
     * @return array<int, array{
     *     household_code: string,
     *     purok_code: string,
     *     address: string,
     *     household_head_name: string,
     *     notes: string,
     * }>
     */
    public static function householdsData(): array
    {
        return [
            // --- Purok 1 (Sitio Centro) ---
            [
                'household_code' => 'HH-P1-001',
                'purok_code' => 'purok-1',
                'address' => '104 Magsaysay St., Sitio Centro, Purok 1, Sta. Felomina',
                'household_head_name' => 'Juanito Mendoza Dela Cruz',
                'notes' => 'Monthly Income: ₱22,000 (Low-Middle Income, small business/agri); 3 members.',
            ],
            [
                'household_code' => 'HH-P1-002',
                'purok_code' => 'purok-1',
                'address' => '212 Rizal Street, Sitio Centro, Purok 1, Sta. Felomina',
                'household_head_name' => 'Roberto Gomez Santos',
                'notes' => 'Monthly Income: ₱16,000 (Low Income, transport services); 4 members.',
            ],
            [
                'household_code' => 'HH-P1-003',
                'purok_code' => 'purok-1',
                'address' => '045 Quezon Boulevard, Sitio Centro, Purok 1, Sta. Felomina',
                'household_head_name' => 'Elena Villafuerte Alcantara',
                'notes' => 'Monthly Income: ₱14,000 (Low Income, retail & pension); 3 members.',
            ],
            [
                'household_code' => 'HH-P1-004',
                'purok_code' => 'purok-1',
                'address' => '088 Mabini Extension, Sitio Centro, Purok 1, Sta. Felomina',
                'household_head_name' => 'Danilo Salcedo Morales',
                'notes' => 'Monthly Income: ₱32,000 (Middle Income, government pension); 2 members.',
            ],

            // --- Purok 2 (Sitio Malipayon) ---
            [
                'household_code' => 'HH-P2-001',
                'purok_code' => 'purok-2',
                'address' => '115 Malipayon Street, Sitio Malipayon, Purok 2, Sta. Felomina',
                'household_head_name' => 'Manuel Castro Bautista',
                'notes' => 'Monthly Income: ₱19,000 (Low Income, carpentry & trades); 4 members.',
            ],
            [
                'household_code' => 'HH-P2-002',
                'purok_code' => 'purok-2',
                'address' => '072 Acacia Lane, Sitio Malipayon, Purok 2, Sta. Felomina',
                'household_head_name' => 'Cristina Velasco Reyes',
                'notes' => 'Monthly Income: ₱35,000 (Middle Income, education sector); 3 members.',
            ],
            [
                'household_code' => 'HH-P2-003',
                'purok_code' => 'purok-2',
                'address' => '034 Narra Drive, Sitio Malipayon, Purok 2, Sta. Felomina',
                'household_head_name' => 'Francisco Beltran Dimaculangan',
                'notes' => 'Monthly Income: ₱15,000 (Low Income, agricultural wage); 3 members.',
            ],
            [
                'household_code' => 'HH-P2-004',
                'purok_code' => 'purok-2',
                'address' => '098 Maharlika Road, Sitio Malipayon, Purok 2, Sta. Felomina',
                'household_head_name' => 'Rodrigo Villanueva Magbanua',
                'notes' => 'Monthly Income: ₱18,000 (Low Income, security services); 2 members.',
            ],

            // --- Purok 3 (National Highway Junction) ---
            [
                'household_code' => 'HH-P3-001',
                'purok_code' => 'purok-3',
                'address' => '018 National Highway Km 114, Purok 3, Sta. Felomina',
                'household_head_name' => 'Antonio Salazar Flores',
                'notes' => 'Monthly Income: ₱25,000 (Middle Income, auto services); 3 members.',
            ],
            [
                'household_code' => 'HH-P3-002',
                'purok_code' => 'purok-3',
                'address' => '056 Highway Bypass Road, Purok 3, Sta. Felomina',
                'household_head_name' => 'Ramon Mercado Catapang',
                'notes' => 'Monthly Income: ₱13,500 (Low Income, peace & security stipend); 3 members.',
            ],
            [
                'household_code' => 'HH-P3-003',
                'purok_code' => 'purok-3',
                'address' => '142 Terminal Road, Purok 3, Sta. Felomina',
                'household_head_name' => 'Ernesto Gomez Navarro',
                'notes' => 'Monthly Income: ₱20,000 (Low-Middle Income, tire service); 4 members.',
            ],
            [
                'household_code' => 'HH-P3-004',
                'purok_code' => 'purok-3',
                'address' => '089 Junction Commercial Strip, Purok 3, Sta. Felomina',
                'household_head_name' => 'Marites Soriano Villafuerte',
                'notes' => 'Monthly Income: ₱28,000 (Middle Income, food enterprise); 2 members.',
            ],

            // --- Purok 4 (Riverside Zone) ---
            [
                'household_code' => 'HH-P4-001',
                'purok_code' => 'purok-4',
                'address' => '023 Riverside Bank Road, Purok 4, Sta. Felomina',
                'household_head_name' => 'Jaime Fernandez Perez',
                'notes' => 'Monthly Income: ₱17,000 (Low Income, rice & vegetable farming); 4 members.',
            ],
            [
                'household_code' => 'HH-P4-002',
                'purok_code' => 'purok-4',
                'address' => '077 Old River Crossing, Purok 4, Sta. Felomina',
                'household_head_name' => 'Rogelio Mendoza Tolentino',
                'notes' => 'Monthly Income: ₱9,000 (Indigent / Pensioner bracket, senior pension); 2 members.',
            ],
            [
                'household_code' => 'HH-P4-003',
                'purok_code' => 'purok-4',
                'address' => '105 Riverview Pathway, Purok 4, Sta. Felomina',
                'household_head_name' => 'Andres Salazar Macaraeg',
                'notes' => 'Monthly Income: ₱14,500 (Low Income, rubber harvest); 3 members.',
            ],
            [
                'household_code' => 'HH-P4-004',
                'purok_code' => 'purok-4',
                'address' => '048 Fishery Lane, Purok 4, Sta. Felomina',
                'household_head_name' => 'Leonora Bautista Salvador',
                'notes' => 'Monthly Income: ₱16,000 (Low Income, barangay health honorarium & poultry); 3 members.',
            ],

            // --- Purok 5 (Sitio Maharlika) ---
            [
                'household_code' => 'HH-P5-001',
                'purok_code' => 'purok-5',
                'address' => '031 Maharlika Central, Purok 5, Sta. Felomina',
                'household_head_name' => 'Fernando Gomez Custodio',
                'notes' => 'Monthly Income: ₱21,000 (Low-Middle Income, fabrication); 3 members.',
            ],
            [
                'household_code' => 'HH-P5-002',
                'purok_code' => 'purok-5',
                'address' => '064 Sitio Maharlika Upper, Purok 5, Sta. Felomina',
                'household_head_name' => 'Divina Mercado Gonzales',
                'notes' => 'Monthly Income: ₱55,000 (High Income, overseas remittance); 4 members.',
            ],
            [
                'household_code' => 'HH-P5-003',
                'purok_code' => 'purok-5',
                'address' => '092 Maharlika Valley, Purok 5, Sta. Felomina',
                'household_head_name' => 'Marivic Soriano Ramos',
                'notes' => 'Monthly Income: ₱13,000 (Low Income, seamstress); 2 members.',
            ],
            [
                'household_code' => 'HH-P5-004',
                'purok_code' => 'purok-5',
                'address' => '118 Maharlika Feeder Road, Purok 5, Sta. Felomina',
                'household_head_name' => 'Jaime Tolentino Aquino',
                'notes' => 'Monthly Income: ₱18,500 (Low Income, corn & banana farming); 3 members.',
            ],

            // --- Purok 6 (Upper Hills / Sitio San Isidro) ---
            [
                'household_code' => 'HH-P6-001',
                'purok_code' => 'purok-6',
                'address' => '014 Upland Ridge, Sitio San Isidro, Purok 6, Sta. Felomina',
                'household_head_name' => 'Remedios Villafuerte Mendoza',
                'notes' => 'Monthly Income: ₱10,500 (Indigent / Pensioner bracket, senior pension); 2 members.',
            ],
            [
                'household_code' => 'HH-P6-002',
                'purok_code' => 'purok-6',
                'address' => '042 San Isidro Trail, Sitio San Isidro, Purok 6, Sta. Felomina',
                'household_head_name' => 'Carmela Morales Garcia',
                'notes' => 'Monthly Income: ₱24,000 (Low-Middle Income, rubber & small retail); 4 members.',
            ],
            [
                'household_code' => 'HH-P6-003',
                'purok_code' => 'purok-6',
                'address' => '068 Mountain View Road, Sitio San Isidro, Purok 6, Sta. Felomina',
                'household_head_name' => 'Jocelyn Catapang Soriano',
                'notes' => 'Monthly Income: ₱26,000 (Middle Income, fruit orchards); 3 members.',
            ],
            [
                'household_code' => 'HH-P6-004',
                'purok_code' => 'purok-6',
                'address' => '095 Forest Reserve Road, Sitio San Isidro, Purok 6, Sta. Felomina',
                'household_head_name' => 'Rosalie Alcantara Valenzuela',
                'notes' => 'Monthly Income: ₱30,000 (Middle Income, healthcare & coffee); 3 members.',
            ],

            // --- Purok 7 (Sitio Pag-asa) ---
            [
                'household_code' => 'HH-P7-001',
                'purok_code' => 'purok-7',
                'address' => '028 Pag-asa Access Road, Sitio Pag-asa, Purok 7, Sta. Felomina',
                'household_head_name' => 'Rowena Perez Hernandez',
                'notes' => 'Monthly Income: ₱23,000 (Low-Middle Income, logistics & community service); 3 members.',
            ],
            [
                'household_code' => 'HH-P7-002',
                'purok_code' => 'purok-7',
                'address' => '055 Sitio Pag-asa Proper, Purok 7, Sta. Felomina',
                'household_head_name' => 'Angelica Navarro Pascual',
                'notes' => 'Monthly Income: ₱15,500 (Low Income, retail & livestock); 3 members.',
            ],
            [
                'household_code' => 'HH-P7-003',
                'purok_code' => 'purok-7',
                'address' => '083 Boundary Marker Street, Sitio Pag-asa, Purok 7, Sta. Felomina',
                'household_head_name' => 'Andres Ramos Del Rosario',
                'notes' => 'Monthly Income: ₱27,000 (Middle Income, construction); 4 members.',
            ],
            [
                'household_code' => 'HH-P7-004',
                'purok_code' => 'purok-7',
                'address' => '112 Sitio Pag-asa Edge, Purok 7, Sta. Felomina',
                'household_head_name' => 'Teresa Bautista Castillo',
                'notes' => 'Monthly Income: ₱42,000 (Middle-High Income, OFW remittance & pension); 2 members.',
            ],
        ];
    }

    /**
     * Complete 84-Resident Dataset Specification
     *
     * @return array<int, array{
     *     resident_code: string,
     *     purok_code: string,
     *     household_code: string,
     *     first_name: string,
     *     middle_name: ?string,
     *     last_name: string,
     *     suffix: ?string,
     *     sex: string,
     *     birthdate: string,
     *     civil_status: string,
     *     contact_number: ?string,
     *     address: string,
     *     is_registered_voter: bool,
     *     status: ResidentStatus,
     *     birthplace: ?string,
     *     occupation: ?string,
     *     remarks: ?string,
     *     is_head_of_household: bool,
     *     relationship_to_head: string,
     * }>
     */
    public static function residentsData(): array
    {
        return [
            // ==========================================
            // PUROK 1 (Sitio Centro — 12 Residents)
            // ==========================================
            // HH-P1-001 (Dela Cruz)
            [
                'resident_code' => 'RES-P1-001',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-001',
                'first_name' => 'Juanito',
                'middle_name' => 'Mendoza',
                'last_name' => 'Dela Cruz',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1978-05-14',
                'civil_status' => 'married',
                'contact_number' => '0917-234-1101',
                'address' => '104 Magsaysay St., Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Farmer & General Merchandiser',
                'remarks' => 'Head of Household',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P1-002',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-001',
                'first_name' => 'Teresa',
                'middle_name' => 'Garcia',
                'last_name' => 'Dela Cruz',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1982-11-20',
                'civil_status' => 'married',
                'contact_number' => '0917-234-1102',
                'address' => '104 Magsaysay St., Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Kidapawan City, Cotabato',
                'occupation' => 'Sari-Sari Store Manager',
                'remarks' => 'Spouse',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P1-003',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-001',
                'first_name' => 'Christian',
                'middle_name' => 'Garcia',
                'last_name' => 'Dela Cruz',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2006-03-15',
                'civil_status' => 'single',
                'contact_number' => '0917-234-1103',
                'address' => '104 Magsaysay St., Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'College Student (BS Agriculture)',
                'remarks' => 'Child',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // HH-P1-002 (Santos)
            [
                'resident_code' => 'RES-P1-004',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-002',
                'first_name' => 'Roberto',
                'middle_name' => 'Gomez',
                'last_name' => 'Santos',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1980-08-25',
                'civil_status' => 'married',
                'contact_number' => '0920-345-2201',
                'address' => '212 Rizal Street, Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Tricycle Driver',
                'remarks' => 'Head of Household',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P1-005',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-002',
                'first_name' => 'Marivic',
                'middle_name' => 'Ramos',
                'last_name' => 'Santos',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1984-02-14',
                'civil_status' => 'married',
                'contact_number' => '0920-345-2202',
                'address' => '212 Rizal Street, Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Bansalan, Davao del Sur',
                'occupation' => 'Homemaker / Dressmaker',
                'remarks' => 'Spouse',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P1-006',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-002',
                'first_name' => 'Kevin',
                'middle_name' => 'Ramos',
                'last_name' => 'Santos',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2008-01-10',
                'civil_status' => 'single',
                'contact_number' => '0920-345-2203',
                'address' => '212 Rizal Street, Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Senior High School Student',
                'remarks' => 'Child; Registered youth voter',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],
            [
                'resident_code' => 'RES-P1-007',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-002',
                'first_name' => 'Bea',
                'middle_name' => 'Ramos',
                'last_name' => 'Santos',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '2012-07-22',
                'civil_status' => 'single',
                'contact_number' => null,
                'address' => '212 Rizal Street, Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Elementary Student',
                'remarks' => 'Child; Minor dependent',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // HH-P1-003 (Alcantara)
            [
                'resident_code' => 'RES-P1-008',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-003',
                'first_name' => 'Elena',
                'middle_name' => 'Villafuerte',
                'last_name' => 'Alcantara',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1960-04-18',
                'civil_status' => 'widowed',
                'contact_number' => '0928-456-3301',
                'address' => '045 Quezon Boulevard, Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Sari-Sari Store Owner',
                'remarks' => 'Head of Household; Senior Citizen',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P1-009',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-003',
                'first_name' => 'Antonio',
                'middle_name' => 'Perez',
                'last_name' => 'Alcantara',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1957-10-12',
                'civil_status' => 'married',
                'contact_number' => null,
                'address' => '045 Quezon Boulevard, Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Deceased,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Former Barangay Tanod',
                'remarks' => 'Deceased spouse (passed away 2024)',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P1-010',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-003',
                'first_name' => 'Grace',
                'middle_name' => 'Villafuerte',
                'last_name' => 'Alcantara',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1992-09-05',
                'civil_status' => 'single',
                'contact_number' => '0928-456-3302',
                'address' => '045 Quezon Boulevard, Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Barangay Daycare Volunteer',
                'remarks' => 'Child; Dependent Caretaker',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // HH-P1-004 (Morales)
            [
                'resident_code' => 'RES-P1-011',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-004',
                'first_name' => 'Danilo',
                'middle_name' => 'Salcedo',
                'last_name' => 'Morales',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1958-12-03',
                'civil_status' => 'married',
                'contact_number' => '0939-567-4401',
                'address' => '088 Mabini Extension, Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Tulunan, Cotabato',
                'occupation' => 'Retired Public School Teacher',
                'remarks' => 'Head of Household; Senior Citizen',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P1-012',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-004',
                'first_name' => 'Jason',
                'middle_name' => 'Aquino',
                'last_name' => 'Morales',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1993-06-28',
                'civil_status' => 'single',
                'contact_number' => '0939-567-4402',
                'address' => '088 Mabini Extension, Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Inactive,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Merchant Seaman',
                'remarks' => 'Child; Deployed on international cargo vessel',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // ==========================================
            // PUROK 2 (Sitio Malipayon — 12 Residents)
            // ==========================================
            // HH-P2-001 (Bautista)
            [
                'resident_code' => 'RES-P2-001',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-001',
                'first_name' => 'Manuel',
                'middle_name' => 'Castro',
                'last_name' => 'Bautista',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1976-03-22',
                'civil_status' => 'married',
                'contact_number' => '0918-123-5501',
                'address' => '115 Malipayon Street, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Kidapawan City, Cotabato',
                'occupation' => 'Master Carpenter',
                'remarks' => 'Head of Household',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P2-002',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-001',
                'first_name' => 'Remedios',
                'middle_name' => 'Santos',
                'last_name' => 'Bautista',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1979-09-14',
                'civil_status' => 'married',
                'contact_number' => '0918-123-5502',
                'address' => '115 Malipayon Street, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Food Vendor',
                'remarks' => 'Spouse',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P2-003',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-001',
                'first_name' => 'Mark Lester',
                'middle_name' => 'Santos',
                'last_name' => 'Bautista',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2004-11-08',
                'civil_status' => 'single',
                'contact_number' => '0918-123-5503',
                'address' => '115 Malipayon Street, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Vocational Welder Apprentice',
                'remarks' => 'Child',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],
            [
                'resident_code' => 'RES-P2-004',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-001',
                'first_name' => 'Princess Joy',
                'middle_name' => 'Santos',
                'last_name' => 'Bautista',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '2010-05-30',
                'civil_status' => 'single',
                'contact_number' => null,
                'address' => '115 Malipayon Street, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'High School Student',
                'remarks' => 'Child; Minor dependent',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // HH-P2-002 (Reyes)
            [
                'resident_code' => 'RES-P2-005',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-002',
                'first_name' => 'Cristina',
                'middle_name' => 'Velasco',
                'last_name' => 'Reyes',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1985-06-18',
                'civil_status' => 'married',
                'contact_number' => '0922-234-6601',
                'address' => '072 Acacia Lane, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Elementary School Teacher',
                'remarks' => 'Head of Household',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P2-006',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-002',
                'first_name' => 'Eduardo',
                'middle_name' => 'Tan',
                'last_name' => 'Reyes',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1983-01-27',
                'civil_status' => 'married',
                'contact_number' => '0922-234-6602',
                'address' => '072 Acacia Lane, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Digos City, Davao del Sur',
                'occupation' => 'Motorcycle Repair Specialist',
                'remarks' => 'Spouse',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P2-007',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-002',
                'first_name' => 'Ethan John',
                'middle_name' => 'Velasco',
                'last_name' => 'Reyes',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2014-04-11',
                'civil_status' => 'single',
                'contact_number' => null,
                'address' => '072 Acacia Lane, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Elementary Student',
                'remarks' => 'Child; Minor dependent',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // HH-P2-003 (Dimaculangan)
            [
                'resident_code' => 'RES-P2-008',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-003',
                'first_name' => 'Francisco',
                'middle_name' => 'Beltran',
                'last_name' => 'Dimaculangan',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1974-11-05',
                'civil_status' => 'married',
                'contact_number' => '0930-345-7701',
                'address' => '034 Narra Drive, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Rubber Farmer & Tapper',
                'remarks' => 'Head of Household',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P2-009',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-003',
                'first_name' => 'Luzviminda',
                'middle_name' => 'Ramos',
                'last_name' => 'Dimaculangan',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1978-08-19',
                'civil_status' => 'married',
                'contact_number' => '0930-345-7702',
                'address' => '034 Narra Drive, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Agricultural Caretaker',
                'remarks' => 'Spouse',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P2-010',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-003',
                'first_name' => 'Gerald',
                'middle_name' => 'Ramos',
                'last_name' => 'Dimaculangan',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2005-12-02',
                'civil_status' => 'single',
                'contact_number' => '0930-345-7703',
                'address' => '034 Narra Drive, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Farm Laborer',
                'remarks' => 'Child; Unregistered young adult',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // HH-P2-004 (Magbanua)
            [
                'resident_code' => 'RES-P2-011',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-004',
                'first_name' => 'Rodrigo',
                'middle_name' => 'Villanueva',
                'last_name' => 'Magbanua',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1977-07-09',
                'civil_status' => 'separated',
                'contact_number' => '0947-456-8801',
                'address' => '098 Maharlika Road, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Columbio, Sultan Kudarat',
                'occupation' => 'Private Security Guard',
                'remarks' => 'Head of Household; Separated',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P2-012',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-004',
                'first_name' => 'Angelica',
                'middle_name' => 'Cruz',
                'last_name' => 'Magbanua',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '2003-02-24',
                'civil_status' => 'single',
                'contact_number' => '0947-456-8802',
                'address' => '098 Maharlika Road, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Transferred,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Call Center Agent',
                'remarks' => 'Child; Transferred to Kidapawan City for employment',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // ==========================================
            // PUROK 3 (National Highway Junction — 12 Residents)
            // ==========================================
            // HH-P3-001 (Flores)
            [
                'resident_code' => 'RES-P3-001',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-001',
                'first_name' => 'Antonio',
                'middle_name' => 'Salazar',
                'last_name' => 'Flores',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1975-05-30',
                'civil_status' => 'married',
                'contact_number' => '0919-567-9901',
                'address' => '018 National Highway Km 114, Purok 3, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Auto Mechanic & Shop Owner',
                'remarks' => 'Head of Household',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P3-002',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-001',
                'first_name' => 'Clarita',
                'middle_name' => 'David',
                'last_name' => 'Flores',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1980-10-14',
                'civil_status' => 'married',
                'contact_number' => '0919-567-9902',
                'address' => '018 National Highway Km 114, Purok 3, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => "M'lang, Cotabato",
                'occupation' => 'Auto Parts Cashier',
                'remarks' => 'Spouse',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P3-003',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-001',
                'first_name' => 'Antonette',
                'middle_name' => 'David',
                'last_name' => 'Flores',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '2007-08-20',
                'civil_status' => 'single',
                'contact_number' => '0919-567-9903',
                'address' => '018 National Highway Km 114, Purok 3, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'IT College Student',
                'remarks' => 'Child; Youth voter',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // HH-P3-002 (Catapang)
            [
                'resident_code' => 'RES-P3-004',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-002',
                'first_name' => 'Ramon',
                'middle_name' => 'Mercado',
                'last_name' => 'Catapang',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1970-12-16',
                'civil_status' => 'married',
                'contact_number' => '0921-678-0011',
                'address' => '056 Highway Bypass Road, Purok 3, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Chief Barangay Tanod',
                'remarks' => 'Head of Household; Tanod Deputy',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P3-005',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-002',
                'first_name' => 'Teresita',
                'middle_name' => 'Ocampo',
                'last_name' => 'Catapang',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1973-04-02',
                'civil_status' => 'married',
                'contact_number' => '0921-678-0012',
                'address' => '056 Highway Bypass Road, Purok 3, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Community Tailor',
                'remarks' => 'Spouse',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P3-006',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-002',
                'first_name' => 'Raymond',
                'middle_name' => 'Ocampo',
                'last_name' => 'Catapang',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2000-09-18',
                'civil_status' => 'single',
                'contact_number' => '0921-678-0013',
                'address' => '056 Highway Bypass Road, Purok 3, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Delivery Driver',
                'remarks' => 'Child',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // HH-P3-003 (Navarro)
            [
                'resident_code' => 'RES-P3-007',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-003',
                'first_name' => 'Ernesto',
                'middle_name' => 'Gomez',
                'last_name' => 'Navarro',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1969-02-28',
                'civil_status' => 'married',
                'contact_number' => '0932-789-1122',
                'address' => '142 Terminal Road, Purok 3, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Kidapawan City, Cotabato',
                'occupation' => 'Vulcanizing Shop Operator',
                'remarks' => 'Head of Household',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P3-008',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-003',
                'first_name' => 'Rosanna',
                'middle_name' => 'Cruz',
                'last_name' => 'Navarro',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1972-11-11',
                'civil_status' => 'married',
                'contact_number' => '0932-789-1123',
                'address' => '142 Terminal Road, Purok 3, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Small Eatery Cook',
                'remarks' => 'Spouse',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P3-009',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-003',
                'first_name' => 'Joshua',
                'middle_name' => 'Cruz',
                'last_name' => 'Navarro',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2002-06-04',
                'civil_status' => 'single',
                'contact_number' => '0932-789-1124',
                'address' => '142 Terminal Road, Purok 3, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Auto Electrician',
                'remarks' => 'Child',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],
            [
                'resident_code' => 'RES-P3-010',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-003',
                'first_name' => 'Jasmine',
                'middle_name' => 'Cruz',
                'last_name' => 'Navarro',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '2011-01-25',
                'civil_status' => 'single',
                'contact_number' => null,
                'address' => '142 Terminal Road, Purok 3, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Junior High School Student',
                'remarks' => 'Child; Minor dependent',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // HH-P3-004 (Villafuerte)
            [
                'resident_code' => 'RES-P3-011',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-004',
                'first_name' => 'Marites',
                'middle_name' => 'Soriano',
                'last_name' => 'Villafuerte',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1981-08-07',
                'civil_status' => 'separated',
                'contact_number' => '0945-890-2233',
                'address' => '089 Junction Commercial Strip, Purok 3, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Digos City, Davao del Sur',
                'occupation' => 'Neighborhood Bakery Owner',
                'remarks' => 'Head of Household; Separated',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P3-012',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-004',
                'first_name' => 'Dominic',
                'middle_name' => 'Soriano',
                'last_name' => 'Villafuerte',
                'suffix' => 'Jr.',
                'sex' => 'male',
                'birthdate' => '2001-04-19',
                'civil_status' => 'single',
                'contact_number' => '0945-890-2234',
                'address' => '089 Junction Commercial Strip, Purok 3, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Transferred,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Commercial Sales Agent',
                'remarks' => 'Child; Transferred to Davao City for employment',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // ==========================================
            // PUROK 4 (Riverside Zone — 12 Residents)
            // ==========================================
            // HH-P4-001 (Perez)
            [
                'resident_code' => 'RES-P4-001',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-001',
                'first_name' => 'Jaime',
                'middle_name' => 'Fernandez',
                'last_name' => 'Perez',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1973-10-10',
                'civil_status' => 'married',
                'contact_number' => '0915-901-3344',
                'address' => '023 Riverside Bank Road, Purok 4, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Rice & Vegetable Farmer',
                'remarks' => 'Head of Household',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P4-002',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-001',
                'first_name' => 'Anita',
                'middle_name' => 'Morales',
                'last_name' => 'Perez',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1976-12-05',
                'civil_status' => 'married',
                'contact_number' => '0915-901-3345',
                'address' => '023 Riverside Bank Road, Purok 4, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Tulunan, Cotabato',
                'occupation' => 'Vegetable Wholesaler',
                'remarks' => 'Spouse',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P4-003',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-001',
                'first_name' => 'Darwin',
                'middle_name' => 'Morales',
                'last_name' => 'Perez',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2003-08-14',
                'civil_status' => 'single',
                'contact_number' => '0915-901-3346',
                'address' => '023 Riverside Bank Road, Purok 4, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Farm Machinery Operator',
                'remarks' => 'Child',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],
            [
                'resident_code' => 'RES-P4-004',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-001',
                'first_name' => 'Jenny',
                'middle_name' => 'Morales',
                'last_name' => 'Perez',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '2009-03-29',
                'civil_status' => 'single',
                'contact_number' => null,
                'address' => '023 Riverside Bank Road, Purok 4, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'High School Student',
                'remarks' => 'Child; Minor dependent',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // HH-P4-002 (Tolentino)
            [
                'resident_code' => 'RES-P4-005',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-002',
                'first_name' => 'Rogelio',
                'middle_name' => 'Mendoza',
                'last_name' => 'Tolentino',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1948-03-12',
                'civil_status' => 'married',
                'contact_number' => null,
                'address' => '077 Old River Crossing, Purok 4, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Deceased,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Retired Land Tiller',
                'remarks' => 'Head of Household; Deceased (February 2026)',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P4-006',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-002',
                'first_name' => 'Natividad',
                'middle_name' => 'Santos',
                'last_name' => 'Tolentino',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1952-09-21',
                'civil_status' => 'widowed',
                'contact_number' => '0926-012-4455',
                'address' => '077 Old River Crossing, Purok 4, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Social Pension Beneficiary',
                'remarks' => 'Surviving Spouse; Senior Citizen / Indigent',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Spouse',
            ],

            // HH-P4-003 (Macaraeg)
            [
                'resident_code' => 'RES-P4-007',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-003',
                'first_name' => 'Andres',
                'middle_name' => 'Salazar',
                'last_name' => 'Macaraeg',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1982-04-03',
                'civil_status' => 'married',
                'contact_number' => '0935-123-5566',
                'address' => '105 Riverview Pathway, Purok 4, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Rubber Tapper',
                'remarks' => 'Head of Household',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P4-008',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-003',
                'first_name' => 'Gemma',
                'middle_name' => 'Flores',
                'last_name' => 'Macaraeg',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1986-07-28',
                'civil_status' => 'married',
                'contact_number' => '0935-123-5567',
                'address' => '105 Riverview Pathway, Purok 4, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Kidapawan City, Cotabato',
                'occupation' => 'Fruit Vendor',
                'remarks' => 'Spouse',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P4-009',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-003',
                'first_name' => 'Angelo',
                'middle_name' => 'Flores',
                'last_name' => 'Macaraeg',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2013-10-15',
                'civil_status' => 'single',
                'contact_number' => null,
                'address' => '105 Riverview Pathway, Purok 4, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Elementary Student',
                'remarks' => 'Child; Minor dependent',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // HH-P4-004 (Salvador)
            [
                'resident_code' => 'RES-P4-010',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-004',
                'first_name' => 'Leonora',
                'middle_name' => 'Bautista',
                'last_name' => 'Salvador',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1978-01-17',
                'civil_status' => 'married',
                'contact_number' => '0948-234-6677',
                'address' => '048 Fishery Lane, Purok 4, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Barangay Health Worker (BHW)',
                'remarks' => 'Head of Household; BHW Volunteer',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P4-011',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-004',
                'first_name' => 'Vicente',
                'middle_name' => 'Ramos',
                'last_name' => 'Salvador',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1975-06-23',
                'civil_status' => 'married',
                'contact_number' => '0948-234-6678',
                'address' => '048 Fishery Lane, Purok 4, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Small Poultry Caretaker',
                'remarks' => 'Spouse',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P4-012',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-004',
                'first_name' => 'Karen Joy',
                'middle_name' => 'Ramos',
                'last_name' => 'Salvador',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '2005-02-14',
                'civil_status' => 'single',
                'contact_number' => '0948-234-6679',
                'address' => '048 Fishery Lane, Purok 4, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Nursing Student',
                'remarks' => 'Child; Registered voter',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // ==========================================
            // PUROK 5 (Sitio Maharlika — 12 Residents)
            // ==========================================
            // HH-P5-001 (Custodio)
            [
                'resident_code' => 'RES-P5-001',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-001',
                'first_name' => 'Fernando',
                'middle_name' => 'Gomez',
                'last_name' => 'Custodio',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1979-11-19',
                'civil_status' => 'married',
                'contact_number' => '0917-345-7788',
                'address' => '031 Maharlika Central, Purok 5, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Welder & Metal Fabricator',
                'remarks' => 'Head of Household',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P5-002',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-001',
                'first_name' => 'Cynthia',
                'middle_name' => 'Diaz',
                'last_name' => 'Custodio',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1982-05-24',
                'civil_status' => 'married',
                'contact_number' => '0917-345-7789',
                'address' => '031 Maharlika Central, Purok 5, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Homemaker',
                'remarks' => 'Spouse',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P5-003',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-001',
                'first_name' => 'Bryan',
                'middle_name' => 'Diaz',
                'last_name' => 'Custodio',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2006-08-30',
                'civil_status' => 'single',
                'contact_number' => '0917-345-7790',
                'address' => '031 Maharlika Central, Purok 5, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Technical Vocational Trainee',
                'remarks' => 'Child; Unregistered young adult',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // HH-P5-002 (Gonzales)
            [
                'resident_code' => 'RES-P5-004',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-002',
                'first_name' => 'Divina',
                'middle_name' => 'Mercado',
                'last_name' => 'Gonzales',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1983-09-08',
                'civil_status' => 'married',
                'contact_number' => '+966-50-123-4567',
                'address' => '064 Sitio Maharlika Upper, Purok 5, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Inactive,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Overseas Filipino Worker (Nurse in Riyadh, KSA)',
                'remarks' => 'Head of Household; OFW in Saudi Arabia since 2024',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P5-005',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-002',
                'first_name' => 'Nestor',
                'middle_name' => 'Alcantara',
                'last_name' => 'Gonzales',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1980-03-14',
                'civil_status' => 'married',
                'contact_number' => '0927-456-8899',
                'address' => '064 Sitio Maharlika Upper, Purok 5, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Kidapawan City, Cotabato',
                'occupation' => 'Commercial Van Driver / Family Caretaker',
                'remarks' => 'Spouse; Managing household locally',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P5-006',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-002',
                'first_name' => 'Nicole',
                'middle_name' => 'Mercado',
                'last_name' => 'Gonzales',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '2008-04-12',
                'civil_status' => 'single',
                'contact_number' => '0927-456-8900',
                'address' => '064 Sitio Maharlika Upper, Purok 5, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Grade 12 Senior High Student',
                'remarks' => 'Child; First-time registered voter',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],
            [
                'resident_code' => 'RES-P5-007',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-002',
                'first_name' => 'Nathan',
                'middle_name' => 'Mercado',
                'last_name' => 'Gonzales',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2015-06-25',
                'civil_status' => 'single',
                'contact_number' => null,
                'address' => '064 Sitio Maharlika Upper, Purok 5, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Elementary Student',
                'remarks' => 'Child; Minor dependent',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // HH-P5-003 (Ramos)
            [
                'resident_code' => 'RES-P5-008',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-003',
                'first_name' => 'Marivic',
                'middle_name' => 'Soriano',
                'last_name' => 'Ramos',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1971-07-22',
                'civil_status' => 'widowed',
                'contact_number' => '0938-567-9900',
                'address' => '092 Maharlika Valley, Purok 5, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Master Tailor & Dressmaker',
                'remarks' => 'Head of Household; Solo Parent',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P5-009',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-003',
                'first_name' => 'Paolo',
                'middle_name' => 'Soriano',
                'last_name' => 'Ramos',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2001-12-10',
                'civil_status' => 'single',
                'contact_number' => '0938-567-9901',
                'address' => '092 Maharlika Valley, Purok 5, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Agricultural Supply Clerk',
                'remarks' => 'Child',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // HH-P5-004 (Aquino)
            [
                'resident_code' => 'RES-P5-010',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-004',
                'first_name' => 'Jaime',
                'middle_name' => 'Tolentino',
                'last_name' => 'Aquino',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1968-01-30',
                'civil_status' => 'married',
                'contact_number' => '0949-678-0012',
                'address' => '118 Maharlika Feeder Road, Purok 5, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Corn & Banana Grower',
                'remarks' => 'Head of Household',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P5-011',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-004',
                'first_name' => 'Corazon',
                'middle_name' => 'Beltran',
                'last_name' => 'Aquino',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1972-04-16',
                'civil_status' => 'married',
                'contact_number' => '0949-678-0013',
                'address' => '118 Maharlika Feeder Road, Purok 5, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Produce Trader',
                'remarks' => 'Spouse',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P5-012',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-004',
                'first_name' => 'Mark Anthony',
                'middle_name' => 'Beltran',
                'last_name' => 'Aquino',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1999-10-05',
                'civil_status' => 'single',
                'contact_number' => '0949-678-0014',
                'address' => '118 Maharlika Feeder Road, Purok 5, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Heavy Equipment Operator',
                'remarks' => 'Child',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // ==========================================
            // PUROK 6 (Upper Hills / Sitio San Isidro — 12 Residents)
            // ==========================================
            // HH-P6-001 (Mendoza)
            [
                'resident_code' => 'RES-P6-001',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-001',
                'first_name' => 'Remedios',
                'middle_name' => 'Villafuerte',
                'last_name' => 'Mendoza',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1950-02-15',
                'civil_status' => 'widowed',
                'contact_number' => '0916-789-1133',
                'address' => '014 Upland Ridge, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Backyard Gardener & Pensioner',
                'remarks' => 'Head of Household; Senior Citizen / PWD',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P6-002',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-001',
                'first_name' => 'Felipe',
                'middle_name' => 'Castro',
                'last_name' => 'Mendoza',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1946-08-20',
                'civil_status' => 'widowed',
                'contact_number' => null,
                'address' => '014 Upland Ridge, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Deceased,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Retired Farmer',
                'remarks' => 'Brother / Relative; Deceased (late 2025)',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Relative',
            ],

            // HH-P6-002 (Garcia)
            [
                'resident_code' => 'RES-P6-003',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-002',
                'first_name' => 'Carmela',
                'middle_name' => 'Morales',
                'last_name' => 'Garcia',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1974-10-09',
                'civil_status' => 'married',
                'contact_number' => '0928-890-2244',
                'address' => '042 San Isidro Trail, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Rubber Planter & Storekeeper',
                'remarks' => 'Head of Household',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P6-004',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-002',
                'first_name' => 'Rolando',
                'middle_name' => 'Dela Cruz',
                'last_name' => 'Garcia',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1971-05-18',
                'civil_status' => 'married',
                'contact_number' => '0928-890-2245',
                'address' => '042 San Isidro Trail, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Kidapawan City, Cotabato',
                'occupation' => 'Plantation Overseer',
                'remarks' => 'Spouse',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P6-005',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-002',
                'first_name' => 'Rona',
                'middle_name' => 'Morales',
                'last_name' => 'Garcia',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1998-03-25',
                'civil_status' => 'single',
                'contact_number' => '0928-890-2246',
                'address' => '042 San Isidro Trail, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Pharmacy Assistant',
                'remarks' => 'Child',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],
            [
                'resident_code' => 'RES-P6-006',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-002',
                'first_name' => 'Rochelle',
                'middle_name' => 'Morales',
                'last_name' => 'Garcia',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '2000-11-14',
                'civil_status' => 'married',
                'contact_number' => '0928-890-2247',
                'address' => '042 San Isidro Trail, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Transferred,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Administrative Clerk',
                'remarks' => 'Child; Transferred to Digos City after marriage',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // HH-P6-003 (Soriano)
            [
                'resident_code' => 'RES-P6-007',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-003',
                'first_name' => 'Jocelyn',
                'middle_name' => 'Catapang',
                'last_name' => 'Soriano',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1980-08-11',
                'civil_status' => 'married',
                'contact_number' => '0939-901-3355',
                'address' => '068 Mountain View Road, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Fruit Grower (Durian & Marang)',
                'remarks' => 'Head of Household',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P6-008',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-003',
                'first_name' => 'Noel',
                'middle_name' => 'Dimaculangan',
                'last_name' => 'Soriano',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1977-01-22',
                'civil_status' => 'married',
                'contact_number' => '0939-901-3356',
                'address' => '068 Mountain View Road, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Orchard Supervisor',
                'remarks' => 'Spouse',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P6-009',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-003',
                'first_name' => 'Justin',
                'middle_name' => 'Catapang',
                'last_name' => 'Soriano',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2011-09-03',
                'civil_status' => 'single',
                'contact_number' => null,
                'address' => '068 Mountain View Road, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'High School Student',
                'remarks' => 'Child; Minor dependent',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // HH-P6-004 (Valenzuela)
            [
                'resident_code' => 'RES-P6-010',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-004',
                'first_name' => 'Rosalie',
                'middle_name' => 'Alcantara',
                'last_name' => 'Valenzuela',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1982-12-07',
                'civil_status' => 'married',
                'contact_number' => '0946-012-4466',
                'address' => '095 Forest Reserve Road, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Rural Health Midwife',
                'remarks' => 'Head of Household',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P6-011',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-004',
                'first_name' => 'Gregorio',
                'middle_name' => 'Santos',
                'last_name' => 'Valenzuela',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1979-04-15',
                'civil_status' => 'married',
                'contact_number' => '0946-012-4467',
                'address' => '095 Forest Reserve Road, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Coffee Farm Manager',
                'remarks' => 'Spouse',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P6-012',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-004',
                'first_name' => 'Christian',
                'middle_name' => 'Santos',
                'last_name' => 'Valenzuela',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2007-07-29',
                'civil_status' => 'single',
                'contact_number' => '0946-012-4468',
                'address' => '095 Forest Reserve Road, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Criminology Student',
                'remarks' => 'Child; Youth voter',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // ==========================================
            // PUROK 7 (Sitio Pag-asa — 12 Residents)
            // ==========================================
            // HH-P7-001 (Hernandez)
            [
                'resident_code' => 'RES-P7-001',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-001',
                'first_name' => 'Rowena',
                'middle_name' => 'Perez',
                'last_name' => 'Hernandez',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1977-03-16',
                'civil_status' => 'married',
                'contact_number' => '0918-234-5577',
                'address' => '028 Pag-asa Access Road, Sitio Pag-asa, Purok 7, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => "Purok Women's Association Coordinator",
                'remarks' => 'Head of Household; Community Leader',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P7-002',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-001',
                'first_name' => 'Alberto',
                'middle_name' => 'Flores',
                'last_name' => 'Hernandez',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1975-08-20',
                'civil_status' => 'married',
                'contact_number' => '0918-234-5578',
                'address' => '028 Pag-asa Access Road, Sitio Pag-asa, Purok 7, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Kidapawan City, Cotabato',
                'occupation' => 'Freight Truck Driver',
                'remarks' => 'Spouse',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P7-003',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-001',
                'first_name' => 'Sheila',
                'middle_name' => 'Perez',
                'last_name' => 'Hernandez',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '2004-06-12',
                'civil_status' => 'single',
                'contact_number' => '0918-234-5579',
                'address' => '028 Pag-asa Access Road, Sitio Pag-asa, Purok 7, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Bookkeeper',
                'remarks' => 'Child',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // HH-P7-002 (Pascual)
            [
                'resident_code' => 'RES-P7-004',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-002',
                'first_name' => 'Angelica',
                'middle_name' => 'Navarro',
                'last_name' => 'Pascual',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1984-11-23',
                'civil_status' => 'married',
                'contact_number' => '0929-345-6688',
                'address' => '055 Sitio Pag-asa Proper, Purok 7, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Sari-Sari Store & Feed Dealer',
                'remarks' => 'Head of Household',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P7-005',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-002',
                'first_name' => 'Cesar',
                'middle_name' => 'Gomez',
                'last_name' => 'Pascual',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1981-02-18',
                'civil_status' => 'married',
                'contact_number' => '0929-345-6689',
                'address' => '055 Sitio Pag-asa Proper, Purok 7, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Bansalan, Davao del Sur',
                'occupation' => 'Livestock Raiser (Swine/Poultry)',
                'remarks' => 'Spouse',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P7-006',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-002',
                'first_name' => 'Carlito',
                'middle_name' => 'Navarro',
                'last_name' => 'Pascual',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2012-10-08',
                'civil_status' => 'single',
                'contact_number' => null,
                'address' => '055 Sitio Pag-asa Proper, Purok 7, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Elementary Student',
                'remarks' => 'Child; Minor dependent',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // HH-P7-003 (Del Rosario)
            [
                'resident_code' => 'RES-P7-007',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-003',
                'first_name' => 'Andres',
                'middle_name' => 'Ramos',
                'last_name' => 'Del Rosario',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1973-09-04',
                'civil_status' => 'married',
                'contact_number' => '0933-456-7799',
                'address' => '083 Boundary Marker Street, Sitio Pag-asa, Purok 7, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Construction General Foreman',
                'remarks' => 'Head of Household',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P7-008',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-003',
                'first_name' => 'Maria Fe',
                'middle_name' => 'Santos',
                'last_name' => 'Del Rosario',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1976-05-17',
                'civil_status' => 'married',
                'contact_number' => '0933-456-7800',
                'address' => '083 Boundary Marker Street, Sitio Pag-asa, Purok 7, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Tailoring Contractor',
                'remarks' => 'Spouse',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P7-009',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-003',
                'first_name' => 'Francis',
                'middle_name' => 'Santos',
                'last_name' => 'Del Rosario',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2002-01-29',
                'civil_status' => 'single',
                'contact_number' => '0933-456-7801',
                'address' => '083 Boundary Marker Street, Sitio Pag-asa, Purok 7, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Site Electrician',
                'remarks' => 'Child',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],
            [
                'resident_code' => 'RES-P7-010',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-003',
                'first_name' => 'Patricia',
                'middle_name' => 'Santos',
                'last_name' => 'Del Rosario',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '2005-07-14',
                'civil_status' => 'single',
                'contact_number' => '0933-456-7802',
                'address' => '083 Boundary Marker Street, Sitio Pag-asa, Purok 7, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Transferred,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'University Student (GenSan)',
                'remarks' => 'Child; Transferred to General Santos City for university studies',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],

            // HH-P7-004 (Castillo)
            [
                'resident_code' => 'RES-P7-011',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-004',
                'first_name' => 'Teresa',
                'middle_name' => 'Bautista',
                'last_name' => 'Castillo',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1963-06-30',
                'civil_status' => 'widowed',
                'contact_number' => '0947-567-8811',
                'address' => '112 Sitio Pag-asa Edge, Purok 7, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Retired Seamstress & Caretaker',
                'remarks' => 'Head of Household; Senior Citizen',
                'is_head_of_household' => true,
                'relationship_to_head' => 'Head',
            ],
            [
                'resident_code' => 'RES-P7-012',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-004',
                'first_name' => 'Jeffrey',
                'middle_name' => 'Bautista',
                'last_name' => 'Castillo',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1991-12-14',
                'civil_status' => 'single',
                'contact_number' => '+971-50-987-6543',
                'address' => '112 Sitio Pag-asa Edge, Purok 7, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Inactive,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'HVAC Technician in Dubai, UAE',
                'remarks' => 'Child; OFW in Dubai, UAE since 2023',
                'is_head_of_household' => false,
                'relationship_to_head' => 'Child',
            ],
        ];
    }
}
```

---

## 5. Idempotent Seeding Implementation Pattern

The implementation in `DemoDataSeeder.php` must execute in two distinct stages (Households first, then Residents) to respect foreign key constraints, with strict `updateOrCreate()` semantics and companion `AuditLog` records.

```php
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;

// 1. Resolve Reference Lookups
$puroks = Purok::query()->where('is_active', true)->get()->keyBy('code');
$chairpersons = User::query()
    ->where('role', UserRole::PurokChairperson)
    ->with('purok')
    ->get()
    ->keyBy(fn (User $user): string => $user->purok?->code ?? '');

// 2. Idempotently Seed 28 Households
foreach (self::householdsData() as $hData) {
    $purok = $puroks->get($hData['purok_code']);
    if (! $purok) {
        continue;
    }

    $chairperson = $chairpersons->get($hData['purok_code']);

    $household = Household::updateOrCreate(
        ['household_code' => $hData['household_code']],
        [
            'purok_id' => $purok->id,
            'address' => $hData['address'],
            'household_head_name' => $hData['household_head_name'],
            'notes' => $hData['notes'],
            'created_by_id' => $chairperson?->id,
        ]
    );

    // Record creation audit log if newly created
    if ($household->wasRecentlyCreated && $chairperson) {
        AuditLog::firstOrCreate(
            [
                'auditable_type' => Household::class,
                'auditable_id' => $household->id,
                'action' => 'household_created',
            ],
            [
                'actor_id' => $chairperson->id,
                'summary' => 'Household created.',
                'context' => [
                    'household_code' => $household->household_code,
                    'purok_id' => $household->purok_id,
                ],
            ]
        );
    }
}

// 3. Idempotently Seed 84 Residents
$households = Household::query()->get()->keyBy('household_code');

foreach (self::residentsData() as $rData) {
    $purok = $puroks->get($rData['purok_code']);
    $household = $households->get($rData['household_code']);
    $chairperson = $chairpersons->get($rData['purok_code']);

    if (! $purok || ! $household) {
        continue;
    }

    $resident = Resident::updateOrCreate(
        ['resident_code' => $rData['resident_code']],
        [
            'first_name' => $rData['first_name'],
            'middle_name' => $rData['middle_name'],
            'last_name' => $rData['last_name'],
            'suffix' => $rData['suffix'],
            'sex' => $rData['sex'],
            'birthdate' => $rData['birthdate'],
            'civil_status' => $rData['civil_status'],
            'contact_number' => $rData['contact_number'],
            'address' => $rData['address'],
            'purok_id' => $purok->id,
            'household_id' => $household->id,
            'is_registered_voter' => $rData['is_registered_voter'],
            'status' => $rData['status'],
            'birthplace' => $rData['birthplace'],
            'occupation' => $rData['occupation'],
            'remarks' => $rData['remarks'],
            'created_by_id' => $chairperson?->id,
        ]
    );

    // Record creation audit log if newly created
    if ($resident->wasRecentlyCreated && $chairperson) {
        AuditLog::firstOrCreate(
            [
                'auditable_type' => Resident::class,
                'auditable_id' => $resident->id,
                'action' => 'resident_created',
            ],
            [
                'actor_id' => $chairperson->id,
                'summary' => 'Resident record created.',
                'context' => [
                    'resident_code' => $resident->resident_code,
                    'purok_id' => $resident->purok_id,
                    'household_id' => $resident->household_id,
                    'status' => $resident->status->value,
                ],
            ]
        );

        // Record status change log if non-active status
        if ($resident->status !== ResidentStatus::Active) {
            AuditLog::firstOrCreate(
                [
                    'auditable_type' => Resident::class,
                    'auditable_id' => $resident->id,
                    'action' => 'resident_status_changed',
                ],
                [
                    'actor_id' => $chairperson->id,
                    'summary' => 'Resident status updated to '.$resident->status->label().'.',
                    'context' => [
                        'from' => ResidentStatus::Active->value,
                        'to' => $resident->status->value,
                        'reason' => $rData['remarks'],
                    ],
                ]
            );
        }
    }
}
```

---

## 6. Verification and Acceptance Matrix

| Verification Check | Expected Outcome | Verification Method |
|---|---|---|
| **Purok Coverage** | Exactly 7 Puroks (`purok-1` to `purok-7`) have households and residents | `expect(Household::distinct('purok_id')->count())->toBe(7);` |
| **Household Count** | Exactly 28 households across all 7 puroks (4 per purok) | `expect(Household::count())->toBe(28);` |
| **Resident Count** | Exactly 84 residents across all 7 puroks (12 per purok) | `expect(Resident::count())->toBe(84);` |
| **Head Integrity** | Every household has exactly 1 matching head of household | For each household, `Resident::where('household_id', $h->id)->get()->first(fn($r) => $r->fullName() === $h->household_head_name)` is not null |
| **Enum Completeness** | All 4 `ResidentStatus` cases present (`active`, `transferred`, `deceased`, `inactive`) | `Resident::where('status', ResidentStatus::Active)->count() === 74`, etc. |
| **Civil Statuses** | All 4 civil statuses present (`single`, `married`, `widowed`, `separated`) | `Resident::distinct('civil_status')->count() === 4` |
| **Minor Voter Rule** | 0 minors (under 18) are registered voters | `Resident::whereDate('birthdate', '>', '2008-09-13')->where('is_registered_voter', true)->count() === 0` |
| **Idempotency** | Seeder runs twice with identical counts and zero constraint collisions | `php artisan db:seed --class=DemoDataSeeder` run back-to-back |
| **Report Engine** | `ReportEngine::residentReport()` correctly slices by purok, status, and voter | Filter tests return exact expected counts |
| **Dashboard Metrics** | `DashboardMetricsService::chairpersonSummary($purokId)` returns 12 residents and 4 households | Dashboard cards render exact metrics |
