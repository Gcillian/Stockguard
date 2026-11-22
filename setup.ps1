$ErrorActionPreference = 'Stop'

Write-Host "Stockguard Laravel setup" -ForegroundColor Cyan
$composer = Get-Content -Raw .\composer.json | ConvertFrom-Json
if ($composer.require.'laravel/framework' -ne '^9.52') {
    throw "composer.json masih memakai versi yang salah. Harus laravel/framework ^9.52."
}
if (-not (Test-Path .\artisan)) {
    throw "File artisan tidak ditemukan. Pastikan Anda menjalankan script dari folder laravel-app."
}

if (-not (Test-Path .\.env)) { Copy-Item .\.env.example .\.env }
composer install --no-interaction
if (Select-String -Path .\.env -Pattern '^APP_KEY=$' -Quiet) { php artisan key:generate }
php artisan migrate --force
Write-Host "Migrasi selesai. Untuk database baru, jalankan php artisan db:seed --force sekali, lalu php artisan serve." -ForegroundColor Green
