---
paths:
  - 'phpunit.xml, tests/**'
---

# General

## Override integrasi eksternal di phpunit.xml
Force TURNSTILE_ENABLED=false di phpunit.xml (seperti BCRYPT_ROUNDS/DB_CONNECTION): key produksi dari .env bocor ke test env dan membuat login feature test gagal. Umumkan semua integrasi eksternal (captcha, dsb.) yang di-enable via .env agar test hermetis.
