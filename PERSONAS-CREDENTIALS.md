# Login Personas & Credentials
### The Karen Hospital School of Nursing — demo / test accounts

**App URL:** http://127.0.0.1:8000/login
**Universal password (all accounts):** `12345678`

> These are seeded demo accounts for the bundled database dump. Change the password on any
> real deployment — do not reuse these anywhere live.

---

## 🔑 One account per persona (start here)

| Persona / Role | Email | Password | Portal after login |
|---|---|---|---|
| **Super Admin** | `superadmin@example.com` | `12345678` | `/superadmin/dashboard` |
| **School Admin** | `admin@karenhospital.org` | `12345678` | `/admin/dashboard` |
| **Teacher / Lecturer** | `teacher1@karenhospital.org` | `12345678` | `/teacher/dashboard` |
| **Accountant** | `accountant1@karenhospital.org` | `12345678` | `/accountant/dashboard` |
| **Librarian** | `librarian1@karenhospital.org` | `12345678` | `/librarian/dashboard` |
| **Parent** | `parent1@karenhospital.org` | `12345678` | `/parent/dashboard` |
| **Student** | `student99@karenhospital.org` | `12345678` | `/student/dashboard` |

---

## 👥 Full account ranges (all share password `12345678`)

| Role | Email pattern | Count |
|---|---|---|
| Super Admin | `superadmin@example.com` | 1 |
| School Admin | `admin@karenhospital.org` | 1 |
| Teacher | `teacher1@karenhospital.org` … `teacher25@karenhospital.org` | 25 |
| Accountant | `accountant1@karenhospital.org` … `accountant3@karenhospital.org` | 3 |
| Librarian | `librarian1@karenhospital.org` … `librarian2@karenhospital.org` | 2 |
| Parent | `parent1@karenhospital.org` … `parent100@karenhospital.org` | 100 |
| Student | `student1@karenhospital.org` … `student300@karenhospital.org` | 300 |

---

## 💡 Best accounts for a demo

| To show… | Log in as | Why |
|---|---|---|
| School-wide admin, users, filters | `admin@karenhospital.org` | Full administrative control |
| Create & grade assignments/quizzes | `teacher1@karenhospital.org` | Assigned to all classes |
| A student with graded work & fees | `student99@karenhospital.org` | Has submissions, grades, invoices |
| Finance dashboard, invoices, payroll | `accountant1@karenhospital.org` | Dedicated finance portal |
| Parent tracking a child | `parent1@karenhospital.org` | Linked to seeded student(s) |
| Multi-school / packages / settings | `superadmin@example.com` | Platform-level administration |

---

## 🗄️ Database dump (for import)

A full dump (schema + data, 72 tables) is at:

```
database/dump/ekattor8_full_dump.sql
```

It includes all seeded users, classes, the E-Learning modules (courses, assignments,
question bank, quizzes), and the Finance module (invoices, payments, payroll, budgets, projects).

### Import into a fresh MySQL database

```bash
# 1) create the target database
mysql -u <user> -p -e "CREATE DATABASE ekattor8_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2) import the dump
mysql -u <user> -p ekattor8_dev < database/dump/ekattor8_full_dump.sql
```

### Import into the bundled Docker MySQL (dev)

```bash
docker exec -i ekattor8_db_dev mysql -uekattor8_user -pekattor8_password ekattor8_dev \
  < database/dump/ekattor8_full_dump.sql
```

> After importing, point the app's `.env` at the database (host / port `3307` for the bundled
> Docker MySQL) and log in with any account above.

---

*Prepared by Algotech Labs · The Karen Hospital School of Nursing.*
