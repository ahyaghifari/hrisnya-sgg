# HRIS Performance Management — System Analysis Baseline

## 1. Tujuan Dokumen

Dokumen ini merangkum konsep awal modul **Performance Management / Performance Appraisal** untuk HRIS pada perusahaan holding yang memiliki beberapa unit/perusahaan.

Fokus dokumen:

- Menentukan type penilaian yang menjadi fondasi sistem.
- Menjelaskan perbedaan KPI, Goal, Competency, Achievement, dan Behavior.
- Menentukan konsep master, template, assignment, evaluation, scoring, workflow, dan final result.
- Menjadi baseline untuk analisis lanjutan sebelum implementasi database/API/UI.
- Menjaga agar sistem fleksibel untuk banyak perusahaan/unit tanpa hard-code aturan satu perusahaan.

> Dokumen ini adalah **system analysis baseline**, bukan keputusan final implementasi database.

---

# 2. Konteks Bisnis

Perusahaan merupakan **holding** yang memiliki beberapa unit/perusahaan.

Contoh struktur:

```text
Holding
├── Company A
│   ├── IT
│   ├── Finance
│   └── HR
├── Company B
│   ├── Sales
│   └── Operations
└── Company C
    └── ...
```

Kebutuhan utama:

1. Setiap company dapat memiliki framework penilaian yang berbeda.
2. Holding dapat memiliki standar corporate yang berlaku lintas company.
3. Penilaian tidak selalu terkait dengan position/jabatan.
4. KPI biasanya berkaitan dengan tanggung jawab/hasil posisi.
5. Behavior dapat berlaku untuk seluruh karyawan.
6. Competency dapat berbeda berdasarkan job level, position, atau framework perusahaan.
7. Bobot dan scoring harus configurable.
8. Periode penilaian harus memiliki lifecycle.
9. Evaluator dan workflow harus configurable.
10. Nilai yang sudah final harus memiliki histori/audit trail.

---

# 3. Prinsip Dasar

## 3.1 Tidak semua penilaian mengarah ke Position

Contoh:

- Discipline
- SOP Compliance
- Integrity
- Accountability

dapat berlaku untuk:

- Programmer
- Finance
- HR
- Sales
- Manager

Sehingga hubungan `Position -> Assessment Item` tidak boleh menjadi satu-satunya model.

Position hanya salah satu faktor applicability.

---

# 4. Core Performance Types

Untuk fondasi awal sistem, digunakan **4 core type**:

```text
KPI
GOAL
ACHIEVEMENT
BEHAVIOR
```

Competency tetap merupakan konsep penting dan dapat digunakan dalam framework, tetapi keputusan baseline saat ini memprioritaskan empat type di atas sebagai type utama.

Jika sistem nantinya membutuhkan Competency sebagai type eksplisit, ia dapat ditambahkan tanpa mengubah konsep dasar master/template/evaluation.

---

# 5. Definisi Setiap Type

## 5.1 KPI — Key Performance Indicator

### Definisi

KPI adalah indikator kinerja yang mengukur **hasil pekerjaan secara terukur**.

Pertanyaan yang dijawab:

> "Seberapa baik hasil pekerjaan dicapai dibandingkan target?"

### Karakteristik

KPI umumnya memiliki:

```text
Target
Actual
Achievement
Scoring Rule
Score
Weight
```

### Contoh

Software Engineer:

```text
KPI:
Project Completion

Target:
100%

Actual:
95%

Achievement:
95%

Weight:
30%

Score:
4
```

Contoh lain:

```text
Sales
Revenue Achievement

Finance
Closing Accuracy

HR
Time to Hire

IT
System Availability
```

### Relasi dengan Position

KPI relatif paling dekat dengan position karena KPI sering berasal dari tanggung jawab jabatan.

Namun KPI tidak harus selalu terikat langsung ke position.

---

# 6. GOAL

## Definisi

Goal adalah **sasaran yang ingin dicapai dalam periode tertentu**, biasanya bersifat spesifik dan dapat berupa project, initiative, improvement, atau strategic objective.

Pertanyaan:

> "Apa sasaran spesifik yang ingin dicapai pada periode ini?"

### Contoh

```text
Goal:
Implement Performance Appraisal Module

Start:
2026-01-01

Deadline:
2026-09-30

Target:
Production Ready

Achievement:
100%

Weight:
20%

Score:
5
```

### Perbedaan Goal dan KPI

KPI:

> Mengukur performa/hasil secara berulang atau terstruktur.

Goal:

> Menentukan sasaran spesifik yang ingin dicapai dalam suatu periode.

Contoh:

```text
KPI:
Project Completion
Target: 95%

GOAL:
Implement Performance Appraisal Module
Target: Go Live Q3
```

Goal dapat memiliki struktur target yang lebih fleksibel daripada KPI.

---

# 7. ACHIEVEMENT

## Definisi

Achievement adalah **pencapaian atau kontribusi yang memberikan nilai tambah**, baik di luar pekerjaan rutin maupun sebagai pencapaian penting selama periode performance.

Pertanyaan:

> "Pencapaian atau kontribusi penting apa yang diberikan karyawan?"

### Contoh

```text
Achievement:
HR Reporting Automation

Description:
Membuat automation yang mengurangi pekerjaan manual HR.

Impact:
8 jam/minggu → 1 jam/minggu

Estimated Saving:
7 jam/minggu

Evidence:
Document / Link / Attachment

Score:
5
```

Contoh lain:

- Membuat improvement proses.
- Menghemat biaya.
- Menyelesaikan project strategis.
- Mendapatkan sertifikasi.
- Menjadi mentor.
- Menangani incident penting.
- Membuat inovasi.
- Mendapat apresiasi customer.

### Catatan Penting

Achievement tidak selalu memiliki pola:

```text
Target → Actual
```

seperti KPI.

Achievement lebih cocok memiliki:

```text
Description
Impact
Evidence
Reviewer Assessment
Score
```

Achievement dapat menjadi type tersendiri karena perusahaan ingin memberikan bobot eksplisit terhadap kontribusi/pencapaian tambahan.

---

# 8. BEHAVIOR

## Definisi

Behavior menilai **bagaimana karyawan bekerja**, bukan sekadar apa hasil pekerjaannya.

Pertanyaan:

> "Apakah karyawan bekerja sesuai standar perilaku, nilai, dan aturan perusahaan?"

### Contoh

```text
Discipline
SOP Compliance
Integrity
Accountability
Teamwork
Corporate Values
```

### Contoh indikator

#### Discipline

```text
Level 1:
Sering terlambat dan membutuhkan pengawasan.

Level 2:
Kadang terlambat dan membutuhkan pengingat.

Level 3:
Memenuhi standar kedisiplinan.

Level 4:
Sangat disiplin dan konsisten.

Level 5:
Menjadi role model kedisiplinan.
```

#### SOP Compliance

```text
Level 1:
Sering melanggar SOP.

Level 2:
Sering membutuhkan pengingat.

Level 3:
Mengikuti SOP sesuai standar.

Level 4:
Konsisten menjalankan SOP.

Level 5:
Membantu meningkatkan kepatuhan tim.
```

Behavior umumnya menggunakan rating/indicator dibanding target-actual.

---

# 9. Ringkasan 4 Type

| Type | Fokus | Umumnya Menggunakan | Contoh |
|---|---|---|---|
| KPI | Result | Target, Actual, Achievement | Project Completion |
| GOAL | Specific Target | Progress/Achievement | Go Live HRIS Module |
| ACHIEVEMENT | Contribution | Evidence, Impact, Score | Process Automation |
| BEHAVIOR | How/Way of Working | Indicator, Rating | Discipline |

Konsep sederhananya:

```text
KPI         = hasil terukur
GOAL        = sasaran spesifik
ACHIEVEMENT = kontribusi/pencapaian
BEHAVIOR    = cara bekerja
```

---

# 10. Jangan Menyamakan Type dengan Item

`KPI`, `GOAL`, `ACHIEVEMENT`, dan `BEHAVIOR` adalah **type/classification**.

Sedangkan item adalah hal konkret yang dinilai.

Contoh:

```text
TYPE
├── KPI
├── GOAL
├── ACHIEVEMENT
└── BEHAVIOR
```

Item:

```text
KPI
├── Project Completion
├── Bug Resolution
└── System Availability

GOAL
└── Implement Performance Module

ACHIEVEMENT
└── HR Reporting Automation

BEHAVIOR
├── Discipline
├── SOP Compliance
└── Teamwork
```

---

# 11. Master vs Assignment vs Evaluation

Ini adalah konsep arsitektur utama.

Jangan membuat satu tabel besar yang mencampurkan semuanya.

Gunakan konsep:

```text
MASTER
   ↓
TEMPLATE
   ↓
ASSIGNMENT
   ↓
EVALUATION
   ↓
RESULT
```

## Master

Mendefinisikan apa yang tersedia.

Contoh:

```text
KPI Master
Behavior Master
Scoring Rule
Rating Scale
```

## Template

Menentukan framework penilaian.

Contoh:

```text
Staff Performance 2026

KPI          50%
GOAL         20%
ACHIEVEMENT  10%
BEHAVIOR     20%
```

## Assignment

Menentukan item/template berlaku untuk siapa dan periode apa.

Contoh:

```text
Budi
2026
Staff Performance Template
```

## Evaluation

Menentukan proses penilaian aktual.

Contoh:

```text
Budi
Manager Assessment
Project Completion = 4
Discipline = 5
```

## Result

Hasil akhir yang dihitung.

```text
Final Score = 4.35
Final Rating = Exceeds Expectations
```

---

# 12. Struktur Master

## 12.1 KPI Master

```text
kpi_master

id
company_id
code
name
description
measurement_type
status
```

Contoh:

```text
KPI-001
Project Completion
percentage
```

Master KPI tidak langsung menyimpan employee.

---

## 12.2 Goal

Goal dapat berasal dari master/template atau dibuat secara individual.

Konsep:

```text
goal_definition
```

dan/atau individual goal assignment.

Contoh:

```text
Implement Performance Appraisal Module
Target:
Production Ready
Deadline:
Q3 2026
```

Goal perlu fleksibel karena tidak semua goal memiliki measurement yang sama.

---

## 12.3 Achievement Master / Definition

Achievement dapat menggunakan master category atau langsung dibuat sebagai item pencapaian.

Contoh category:

```text
Innovation
Process Improvement
Cost Saving
Strategic Contribution
Certification
Special Contribution
```

Actual achievement tetap berada di level evaluation/assignment.

---

## 12.4 Behavior Master

```text
behavior_master

id
company_id
code
name
description
status
```

Contoh:

```text
BEH-001
Discipline
```

---

# 13. Indicator

Item tertentu membutuhkan indikator.

Terutama:

```text
BEHAVIOR
```

dan dapat juga digunakan untuk:

```text
COMPETENCY
```

Contoh:

```text
Behavior:
Discipline

Indicators:
Level 1
Level 2
Level 3
Level 4
Level 5
```

Konsep:

```text
assessment_item
      ↓
indicator
      ↓
rating
```

Indikator membantu evaluator memberikan rating berdasarkan definisi yang konsisten.

---

# 14. Rating Scale

Contoh:

```text
1 → Unsatisfactory
2 → Needs Improvement
3 → Meets Expectations
4 → Exceeds Expectations
5 → Outstanding
```

Master:

```text
performance_rating_scales
```

Rating scale sebaiknya configurable.

Jangan hard-code:

```text
if score == 5:
   Outstanding
```

jika perusahaan dapat memiliki skala berbeda.

---

# 15. Scoring Rule

Scoring Rule menentukan bagaimana data diterjemahkan menjadi score.

Contoh KPI:

```text
Achievement < 60%    → Score 1
60–79%               → Score 2
80–99%               → Score 3
100–109%             → Score 4
>=110%               → Score 5
```

Flow:

```text
Target
  ↓
Actual
  ↓
Achievement %
  ↓
Scoring Rule
  ↓
Score
```

Untuk Behavior:

```text
Indicator
  ↓
Evaluator Rating
  ↓
Score
```

Untuk Achievement:

```text
Evidence
  ↓
Reviewer Assessment
  ↓
Score
```

---

# 16. Performance Template

Template adalah **framework penilaian**.

Contoh:

```text
Staff Performance 2026
```

Komponen:

```text
KPI          50%
GOAL         20%
ACHIEVEMENT  10%
BEHAVIOR     20%
```

Database konsep:

```text
performance_templates

id
company_id
name
description
version
status
```

Kemudian:

```text
performance_template_components

id
template_id
type
weight
sequence
required
```

---

# 17. Template Item

Template menentukan item apa yang digunakan.

Contoh:

```text
Staff Performance 2026

KPI
├── Project Completion       30%
└── Bug Resolution            20%

GOAL
└── Individual Goal           20%

ACHIEVEMENT
└── Special Contribution      10%

BEHAVIOR
├── Discipline                5%
├── SOP Compliance             5%
├── Teamwork                   5%
└── Accountability             5%
```

Total:

```text
100%
```

Bobot item lebih spesifik daripada bobot type.

---

# 18. Applicability

Assessment item tidak harus selalu memiliki position.

Applicability dapat berdasarkan:

```text
Company
Department
Position
Job Level
Employee
Template
Period
```

Contoh:

```text
Discipline
→ All Employees

Leadership
→ Manager / Supervisor

Revenue Achievement
→ Sales

Project Completion
→ Software Engineer
```

Semua field applicability tidak harus diisi.

---

# 19. Performance Period

Periode menentukan kapan performance cycle berjalan.

```text
performance_periods

id
company_id
name
start_date
end_date
status
```

Contoh:

```text
2026 Annual Performance
2026-01-01
2026-12-31
```

Lifecycle:

```text
DRAFT
OPEN
IN_PROGRESS
SUBMITTED
REVIEW
CALIBRATION
FINALIZED
CLOSED
```

---

# 20. Employee Evaluation

Ketika period dibuka, system mencari employee yang applicable berdasarkan template/assignment.

Contoh:

```text
Employee:
Budi

Company:
PT Alpha

Position:
Software Engineer

Job Level:
Staff

Period:
2026 Annual

Template:
Staff Performance 2026
```

System membuat:

```text
performance_evaluations
```

Contoh status awal:

```text
NOT_STARTED
```

---

# 21. Evaluation Items

Evaluation kemudian memiliki item konkret:

```text
Performance Evaluation
│
├── KPI
│   ├── Project Completion
│   └── Bug Resolution
│
├── GOAL
│   └── Performance Module
│
├── ACHIEVEMENT
│   └── HR Automation
│
└── BEHAVIOR
    ├── Discipline
    ├── SOP Compliance
    └── Teamwork
```

Konsep tabel:

```text
performance_evaluation_items

id
evaluation_id
type
reference_id
weight
score
comment
```

`reference_id` mengarah ke master/assignment yang sesuai berdasarkan type.

Untuk implementasi production, polymorphic reference perlu dipertimbangkan dengan hati-hati. Alternatif FK terpisah atau unified assessment item dapat dipilih setelah ERD final.

---

# 22. Evaluator

Evaluator tidak boleh di-hard-code hanya sebagai manager.

Possible evaluator:

```text
SELF
MANAGER
SECOND_LEVEL_MANAGER
HR
PEER
SUBORDINATE
CUSTOMER
```

Konsep:

```text
performance_evaluators

id
evaluation_id
evaluator_employee_id
evaluator_type
status
submitted_at
```

Contoh:

```text
Budi
├── Self Assessment
├── Manager Assessment
└── HR Review
```

Untuk 360° assessment, struktur ini masih dapat dikembangkan.

---

# 23. Apakah Self Assessment Masuk Final Score?

Harus configurable.

Contoh:

```text
Self Assessment = 20%
Manager = 80%
```

atau:

```text
Self Assessment = 0%
Manager = 100%
```

Self assessment bisa juga hanya menjadi reference/discussion input dan tidak masuk perhitungan final.

---

# 24. Scoring Calculation

Contoh:

```text
KPI
Score = 4.4
Weight = 50%

GOAL
Score = 4.0
Weight = 20%

ACHIEVEMENT
Score = 5.0
Weight = 10%

BEHAVIOR
Score = 4.25
Weight = 20%
```

Calculation:

```text
4.4 × 50% = 2.20
4.0 × 20% = 0.80
5.0 × 10% = 0.50
4.25 × 20% = 0.85

Final Score = 4.35
```

Kemudian:

```text
4.35
↓
Rating Scale
↓
Exceeds Expectations
```

---

# 25. Weighted Score

Setiap item dapat memiliki weight.

Contoh:

```text
Project Completion
Score = 4
Weight = 30%

Weighted Score = 4 × 30%
               = 1.20
```

Kemudian semua weighted score dijumlahkan.

```text
Final Score =
Σ(score × weight)
```

Weight harus dinormalisasi/ditentukan secara konsisten.

---

# 26. Workflow

Contoh workflow:

```text
Employee
   ↓
Self Assessment
   ↓
Manager Review
   ↓
HR Review
   ↓
Calibration
   ↓
Finalized
```

Konsep:

```text
performance_workflows
performance_workflow_steps
performance_approval_steps
```

Workflow harus configurable per template/company jika kebutuhan perusahaan berbeda.

---

# 27. Calibration

Calibration digunakan untuk review/perbandingan hasil performance antar employee/team.

Contoh:

```text
Manager Score:
Budi = 4.8
Andi = 4.7
Citra = 4.6
```

Setelah calibration:

```text
Budi = 4.5
Andi = 4.3
Citra = 4.2
```

Jangan overwrite nilai mentah.

Simpan:

```text
original_score
calibrated_score
final_score
```

---

# 28. Audit Trail

Performance data bersifat sensitif dan dapat berdampak pada:

- Bonus
- Promotion
- Salary Adjustment
- Talent Management
- Career Development
- Termination

Maka perubahan harus dapat dilacak.

Konsep:

```text
performance_audit_logs

id
evaluation_id
actor_employee_id
action
old_value
new_value
created_at
```

Contoh:

```text
Manager
Changed score
3 → 4
2026-10-15 14:32
```

---

# 29. Arsitektur Flow — Setup

## Phase A: Configuration

```text
Organization Master
        ↓
Assessment Types
        ↓
KPI Master
        ↓
Goal Configuration
        ↓
Achievement Configuration
        ↓
Behavior Master
        ↓
Indicators
        ↓
Rating Scale
        ↓
Scoring Rules
        ↓
Performance Template
        ↓
Template Components
        ↓
Template Items
        ↓
Applicability / Assignment
        ↓
Performance Period
```

---

# 30. Arsitektur Flow — Execution

```text
Performance Period
        ↓
Find Applicable Employees
        ↓
Assign Template
        ↓
Create Evaluation
        ↓
Create Evaluation Items
        ↓
Employee Assessment
        ↓
Manager Assessment
        ↓
HR Review
        ↓
Calculate Score
        ↓
Calibration
        ↓
Finalize
        ↓
Performance Result
```

---

# 31. Contoh End-to-End

Employee:

```text
Budi
Software Engineer
PT Alpha
Staff
```

Template:

```text
Staff Performance 2026
```

Framework:

```text
KPI          50%
GOAL         20%
ACHIEVEMENT  10%
BEHAVIOR     20%
```

Items:

```text
KPI
├── Project Completion       30%
└── Bug Resolution            20%

GOAL
└── Performance Module       20%

ACHIEVEMENT
└── HR Automation             10%

BEHAVIOR
├── Discipline                 5%
├── SOP Compliance              5%
├── Teamwork                    5%
└── Accountability              5%
```

Assessment:

```text
Project Completion = 4
Bug Resolution = 5
Performance Module = 5
HR Automation = 5
Discipline = 4
SOP Compliance = 5
Teamwork = 4
Accountability = 4
```

Calculation:

```text
4 × 30% = 1.20
5 × 20% = 1.00
5 × 20% = 1.00
5 × 10% = 0.50
4 × 5%  = 0.20
5 × 5%  = 0.25
4 × 5%  = 0.20
4 × 5%  = 0.20

Final Score = 4.55
```

Final:

```text
Score:
4.55

Rating:
Outstanding
```

---

# 32. Proposed Core Tables

Baseline tabel yang perlu dianalisis lebih lanjut:

```text
companies
departments
positions
job_levels
employees

performance_periods

performance_templates
performance_template_components
performance_template_items

kpi_master
goal_master / goal_definitions
achievement_master / achievement_categories
behavior_master
behavior_indicators

performance_rating_scales
performance_rating_scale_items
performance_scoring_rules

performance_evaluations
performance_evaluation_items
performance_evaluators
performance_evaluation_results

performance_workflows
performance_workflow_steps
performance_approval_steps

performance_calibrations

performance_audit_logs
```

Tidak semua tabel di atas wajib final. Beberapa dapat digabung setelah ERD dan business rules ditentukan.

---

# 33. Prinsip Database yang Harus Dijaga

## Jangan

```text
employee_performance
score_kpi
score_goal
score_behavior
score_achievement
```

dalam satu tabel besar yang hard-coded.

## Lebih baik

```text
Master
  ↓
Template
  ↓
Assignment
  ↓
Evaluation
  ↓
Evaluation Item
  ↓
Result
```

Dengan demikian perusahaan dapat membuat:

```text
Company A:
KPI + GOAL + ACHIEVEMENT + BEHAVIOR

Company B:
KPI + BEHAVIOR

Company C:
KPI + GOAL + BEHAVIOR
```

tanpa mengubah struktur utama sistem.

---

# 34. Holding-Level Framework

Karena perusahaan adalah holding, framework dapat memiliki scope:

```text
HOLDING
   ↓
COMPANY
   ↓
DEPARTMENT
   ↓
POSITION / JOB LEVEL
   ↓
EMPLOYEE
```

Contoh:

```text
Holding Standard
├── Integrity
├── Discipline
├── Collaboration
└── Corporate Values
```

Company A dapat menambahkan:

```text
Company A
├── Project Completion
├── Technical Quality
└── Customer Focus
```

Sehingga employee dapat menerima kombinasi:

```text
Corporate Assessment
+
Company Assessment
+
Position/Role Assessment
+
Individual Goal/Achievement
```

---

# 35. Core Domain Model

Model konseptual:

```text
                    PERFORMANCE FRAMEWORK
                             │
                ┌────────────┴────────────┐
                │                         │
             TEMPLATE                 MASTER ITEMS
                │                         │
                │             ┌───────────┼───────────┐
                │             │           │           │
                │            KPI         GOAL    ACHIEVEMENT
                │             │                       │
                │          BEHAVIOR                    │
                │             │                       │
                └─────────────┼───────────────────────┘
                              ↓
                         ASSIGNMENT
                              ↓
                         EVALUATION
                              ↓
                    EVALUATION ITEMS
                              ↓
                         ASSESSMENT
                              ↓
                           SCORE
                              ↓
                           WEIGHT
                              ↓
                       FINAL RESULT
```

---

# 36. Kesimpulan Konseptual

Fondasi performance appraisal tidak harus rumit.

Empat type utama:

```text
KPI
GOAL
ACHIEVEMENT
BEHAVIOR
```

mewakili empat perspektif:

```text
KPI
→ hasil terukur

GOAL
→ sasaran spesifik

ACHIEVEMENT
→ kontribusi/pencapaian

BEHAVIOR
→ cara bekerja
```

Kemudian fleksibilitas sistem diperoleh bukan dengan menambah banyak type, tetapi dengan membuat:

```text
Assessment Item
Indicator
Weight
Scoring Rule
Template
Applicability
Evaluator
Workflow
```

sebagai konfigurasi.

Core principle:

> **Type menentukan kategori, Item menentukan apa yang dinilai, Indicator menentukan standar, Weight menentukan kepentingan, Scoring Rule menentukan cara menghitung, Template menentukan kombinasi, Assignment menentukan siapa yang dinilai, dan Evaluation menyimpan proses penilaian aktual.**

---

# 37. Pertanyaan yang Masih Harus Diputuskan Sebelum ERD Final

Sebelum implementasi production, hal berikut perlu ditentukan:

1. Apakah GOAL dan KPI memiliki scoring engine yang sama?
2. Apakah ACHIEVEMENT wajib memiliki weight?
3. Apakah satu employee boleh memiliki multiple Goal?
4. Apakah satu Achievement dapat dimasukkan sebagai evidence ke KPI?
5. Apakah Behavior selalu menggunakan rating 1–5?
6. Apakah scoring rule KPI berbeda per KPI?
7. Apakah weight ditentukan di type, item, atau keduanya?
8. Apakah self assessment masuk final calculation?
9. Apakah manager assessment selalu menjadi final source?
10. Apakah calibration dapat mengubah final score?
11. Apakah template diwariskan dari Holding → Company?
12. Bagaimana employee yang pindah company/position di tengah performance period?
13. Bagaimana employee join/resign di tengah period?
14. Apakah KPI memiliki quarterly/monthly progress?
15. Apakah performance history harus immutable setelah FINALIZED?
16. Apakah satu period dapat memiliki lebih dari satu evaluation?
17. Bagaimana 360° assessment bekerja?
18. Apakah target KPI bisa berubah selama period dan bagaimana audit-nya?
19. Apakah weight item harus selalu total 100%?
20. Apakah company dapat membuat type/item custom di masa depan?

Pertanyaan-pertanyaan ini sebaiknya diselesaikan sebelum membuat ERD final.

---

# 38. Recommended Next Analysis

Urutan analisis berikutnya:

```text
1. Finalisasi definisi 4 Type
        ↓
2. Definisikan Assessment Item
        ↓
3. Definisikan Indicator
        ↓
4. Definisikan Scoring Model
        ↓
5. Definisikan Weight Model
        ↓
6. Definisikan Template
        ↓
7. Definisikan Applicability
        ↓
8. Definisikan Evaluation
        ↓
9. Definisikan Evaluator
        ↓
10. Definisikan Workflow
        ↓
11. Definisikan Calibration
        ↓
12. Buat ERD
        ↓
13. Buat contoh data
        ↓
14. Buat API
        ↓
15. Buat UI/UX
```

**Jangan langsung coding database sebelum poin 1–10 cukup jelas**, karena perubahan business rule pada performance system dapat berdampak besar ke struktur data.
