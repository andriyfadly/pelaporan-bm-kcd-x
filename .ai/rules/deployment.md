---
paths:
  - '**'
---

# Deployment Discipline

## Kode di server hanya boleh masuk lewat git pull
Produksi `/var/www/aset-kcd-x-superapps` (samlosier-server-fisik, https://sidipta-kcd10.my.id) adalah checkout git. Perubahan kode WAJIB lewat commit → push ke `origin/main` → `git pull` di server. Jangan pernah mengedit file tracked langsung di server (scp, sed, nano, heredoc): hasilnya `git status` kotor, dan `deploy.sh` (yang membuka dengan `git pull origin main`) akan gagal atau menimpa kerja manual itu tanpa jejak.

Verifikasi setelah deploy, bukan asumsi:
```bash
git fetch origin && git rev-parse HEAD origin/main   # harus sama
git diff --name-only HEAD                             # harus kosong
git reflog | head -5                                  # harus 'pull origin main: Fast-forward'
```
Hash file bisa dibandingkan langsung: `git show HEAD:<path> | shasum -a 256` vs `shasum -a 256 <path>` di server.

## Yang boleh berbeda di server hanya config runtime
- `.env` (gitignored): kredensial, `APP_LOCALE`, `APP_TIMEZONE`, dsb.
- `bootstrap/cache/*.php` dan `public/build/*`: artefak `config:cache`/`route:cache`/`npm run build`.

Selain itu `git status --porcelain --untracked-files=no` harus kosong. Bila ada drift lokal (mis. `package-lock.json` yang berubah karena npm di server), backup dulu ke `/tmp`, lalu `git checkout -- <file>`; jangan dibiarkan karena `npm ci` menolak lockfile yang tidak sinkron.

## Deploy = `./deploy.sh`, dan node perlu login shell
`node`/`npm` hanya ada lewat nvm, sehingga `ssh host '... ./deploy.sh'` non-interaktif akan gagal di tahap build. Jalankan dengan `bash -lc` dan source nvm lebih dulu. `deploy.sh` sudah menangani `php artisan up` lewat trap EXIT.
