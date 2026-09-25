$ErrorActionPreference = 'Stop'
$statePath = Join-Path $PSScriptRoot 'storage/logs/local-processes.json'
if (-not (Test-Path -LiteralPath $statePath)) { Write-Output 'Nenhum processo registado.'; exit 0 }
$state = Get-Content -Raw -LiteralPath $statePath | ConvertFrom-Json
foreach ($entry in $state.processes) {
    $process = Get-Process -Id $entry.id -ErrorAction SilentlyContinue
    if ($process -and $process.ProcessName -eq 'php' -and $process.StartTime.ToUniversalTime().ToString('o') -eq $entry.started) {
        & taskkill /PID $entry.id /T /F | Out-Null
    }
}
Remove-Item -LiteralPath $statePath
Write-Output 'Processos locais da plataforma encerrados.'
