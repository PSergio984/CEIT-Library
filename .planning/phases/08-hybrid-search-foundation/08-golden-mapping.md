# Golden Set Mapping — Draft Cases → Real Export Ids

**Export snapshot:** `generated_at` = `2026-08-13T11:25:36+00:00` (catalog.json + policies.json)
**Source of ids:** `storage/app/ai-corpus/catalog.json` (30 docs) + `policies.json` (13 docs) — the real export from the dev database.

> **NOTE — dev catalog is faker-seeded.** The dev DB contains 30 faker-generated papers (Latin placeholder titles, generated person names) and 3 policy headers with faker regulation text. The xlsx real-world catalog (`CEIT ACADEMIC PAPER.xlsx`) is guide-only per D-21 and is NOT in the DB. Every draft case below is therefore a **SUBSTITUTION** to a real exported row, with the substitution explicitly labeled. Queries were reworded to match the real titles/people so the golden set measures real retrieval quality. When real catalog data is imported to the DB, this mapping doc + the export command let the golden set be re-mapped cheaply.

## Catalog substitutions (draft P1..P7)

| Draft ref | Draft pattern (xlsx-guided) | Real id | Real code | Real title (faker) | Dept | Year | Type | SUBSTITUTION note |
|---|---|---|---|---|---|---|---|---|
| P1 | "Pigeon: Web-Based Lost and Found System" (IT-25-02) | paper-4 | CEIT-IT-23-01 | Et aperiam iste facilis placeat. | IT | 2023 | Research | SUBSTITUTION: closest IT paper by dept+type |
| P2 | "Web-based Transaction Management System" (IT-25-01) | paper-20 | CEIT-IT-15-01 | Corporis harum consectetur sunt. | IT | 2015 | Feasib | SUBSTITUTION: closest IT paper |
| P3 | "VCC: Valenzuela Commuting Companion" (IT-25-03) | paper-23 | CEIT-IT-11-01 | Delectus esse sunt facere dolores. | IT | 2011 | Feasib | SUBSTITUTION: closest IT paper |
| P4 | "FRIAS: Flood Risk Indicator..." (EE-25-07) | paper-10 | CEIT-EE-25-01 | Laudantium aut tempora sint aperiam. | EE | 2025 | Capstone | SUBSTITUTION: closest EE paper by year |
| P5 | "DALOY: Hydraulic Ram Pump..." (EE-25-05) | paper-30 | CEIT-EE-08-02 | Ratione ipsum iste rerum porro. | EE | 2008 | Feasib | SUBSTITUTION: closest EE paper |
| P6 | "Bamboo Fibers as Reinforcement for Concrete" (CE-14-02) | paper-9 | CEIT-CE-22-01 | Fugit sit illum est. | CE | 2022 | Thesis | SUBSTITUTION: closest CE Thesis |
| P7 | "Groundwater Depletion / Water Pumps" (CE-15-04) | paper-5 | CEIT-EE-15-01 | Expedita sit rerum quis illo. | EE | 2015 | Thesis | SUBSTITUTION: closest Thesis paper |

## Policy substitutions (draft H2-R1..R3, H3-R1..R2)

Real policy headers: `policy-h1` "I.General Information", `policy-h2` "II.Duties and Responsibilities", `policy-h3` "III.Study Room Rules and Regulations" (with regulations `policy-h2-r5..r7` and `policy-h3-r8..r10`).

| Draft ref | Draft header | Real ids | SUBSTITUTION note |
|---|---|---|---|
| H2-R1..R3 | "II. Borrowing Policies" | policy-h2-r5, policy-h2-r6, policy-h2-r7 | SUBSTITUTION: real "II.Duties and Responsibilities" regulations |
| H3-R1..R2 | "III. Library Hours and Conduct" | policy-h3-r8, policy-h3-r9, policy-h3-r10 | SUBSTITUTION: real "III.Study Room Rules and Regulations" regulations |

## Golden case mapping (draft # → real query + ids)

| # | Category | Query (realized) | Corpus | Filters | Relevant (real ids) | Negative |
|---|---|---|---|---|---|---|
| 1 | Exact title | Et aperiam iste facilis placeat. | catalog | — | paper-4 | |
| 2 | Exact title (short) | Fugit sit illum est. | catalog | — | paper-9 | |
| 3 | Catalog code | CEIT-IT-23-01 | catalog | — | paper-4 | |
| 4 | Code family | CEIT-IT | catalog | — | paper-4, paper-20, paper-23, paper-24, paper-26, paper-27, paper-28 | |
| 5 | Paraphrase (topic) | information technology research paper | catalog | — | paper-4, paper-26, paper-27, paper-28 | |
| 6 | Paraphrase (topic) | electrical engineering feasibility study | catalog | — | paper-21, paper-30, paper-20 | |
| 7 | Paraphrase (topic) | civil engineering thesis | catalog | — | paper-9, paper-5, paper-6, paper-7, paper-11, paper-12, paper-25 | |
| 8 | People (adviser) | papers by Kamren Adams | catalog | — | paper-? (matches research_adviser Kamren Adams MD) | |
| 9 | People (adviser, EN) | theses advised by Engr. Chadd Legros | catalog | — | paper-? (technical_adviser) | |
| 10 | People (author) | Bill Beier thesis | catalog | — | paper-? (author Bill Beier) | |
| 11 | People (dean) | papers under Dr. Felix Streich | catalog | — | paper-? (dean) | |
| 12 | Taglish (topic) | may thesis ba kayo tungkol sa electrical? | catalog | — | paper-2, paper-10, paper-19, paper-21, paper-30 | |
| 13 | Taglish (topic) | hanap ko yung project sa civil engineering | catalog | — | paper-1, paper-3, paper-8, paper-9, paper-13, paper-16, paper-17, paper-18, paper-22, paper-25, paper-29 | |
| 14 | Taglish (topic) | may papeles ba kayo sa information technology | catalog | — | paper-4, paper-20, paper-23, paper-24, paper-26, paper-27, paper-28 | |
| 15 | Topic + filter | research paper | catalog | paper_type=Research | paper-4, paper-19, paper-26, paper-27, paper-28 | |
| 16 | Filter narrowing | engineering | catalog | department=Information Technology | paper-4, paper-20, paper-23, paper-24, paper-26, paper-27, paper-28 | |
| 17 | Year + dept | thesis | catalog | year=2025 + dept=Electrical Engineering | paper-10 | |
| 18 | Code + type | feasi | catalog | paper_type=Feasib | paper-20, paper-21, paper-23, paper-30 | |
| 19 | Policy EN | What are the duties and responsibilities in the library? | policy | — | policy-h2-r5, policy-h2-r6, policy-h2-r7 | |
| 20 | Policy EN | What are the study room rules and regulations? | policy | — | policy-h3-r8, policy-h3-r9, policy-h3-r10 | |
| 21 | Policy Taglish | ano ba ang mga rules sa study room? | policy | — | policy-h3-r8, policy-h3-r9, policy-h3-r10 | |
| 22 | Negative | Harry Potter novel | catalog | — | — | ✓ |
| 23 | Negative | recipes for adobo | catalog+policy | — | — | ✓ |
| 24 | Negative policy | how much is the tuition fee | policy | — | — | ✓ |
| 25 | Acronym | CEIT | catalog | — | all 30 papers (any present) | |

> Cases 8-11 depend on per-paper people lookups; the generator resolves them from real metadata at build time (see Task 2 note). Held-out set (excluded from tuning): {8, 12, 15, 21, 25} per the plan.
