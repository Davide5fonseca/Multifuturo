# Atalho para o Laravel Sail no Windows (PowerShell) sem WSL2.
# O script ./vendor/bin/sail só suporta macOS/Linux/WSL2; este wrapper reproduz
# os comandos mais usados sobre docker compose. Uso: .\sail.ps1 artisan migrate
param([Parameter(ValueFromRemainingArguments = $true)] $Args)

$service = "multifuturo.test"
if (-not $Args -or $Args.Count -eq 0) { Write-Host "Uso: .\sail.ps1 up|down|artisan|composer|npm|shell|logs|ps ..."; exit 1 }

$cmd  = $Args[0]
$rest = @()
if ($Args.Count -gt 1) { $rest = $Args[1..($Args.Count - 1)] }

switch ($cmd) {
    "up"       { docker compose up -d @rest }
    "down"     { docker compose down @rest }
    "ps"       { docker compose ps @rest }
    "logs"     { docker compose logs @rest }
    "build"    { docker compose build @rest }
    "artisan"  { docker compose exec -u sail $service php artisan @rest }
    "art"      { docker compose exec -u sail $service php artisan @rest }
    "tinker"   { docker compose exec -u sail $service php artisan tinker @rest }
    "composer" { docker compose exec -u sail $service composer @rest }
    "php"      { docker compose exec -u sail $service php @rest }
    "npm"      { docker compose exec -u sail $service npm @rest }
    "npx"      { docker compose exec -u sail $service npx @rest }
    "test"     { docker compose exec -u sail $service php artisan test @rest }
    "pest"     { docker compose exec -u sail $service ./vendor/bin/pest @rest }
    "shell"    { docker compose exec -u sail $service bash }
    "root"     { docker compose exec -u root $service bash }
    "psql"     { docker compose exec pgsql psql -U sail -d multifuturo @rest }
    "redis"    { docker compose exec redis redis-cli @rest }
    default    { docker compose exec -u sail $service @Args }
}
