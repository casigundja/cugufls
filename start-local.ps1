param([int]$Port = 8000)
$ErrorActionPreference = 'Stop'
$projectDirectory = $PSScriptRoot
Set-Location -LiteralPath $projectDirectory
$phpBinary = (Get-Command php -ErrorAction Stop).Source
$artisanPath = Join-Path $projectDirectory 'artisan'
$logDirectory = Join-Path $projectDirectory 'storage/logs'
$statePath = Join-Path $logDirectory 'local-processes.json'
if (-not (Test-Path -LiteralPath (Join-Path $projectDirectory 'vendor/autoload.php'))) { throw 'Execute composer install antes de iniciar.' }
if (-not (Test-Path -LiteralPath (Join-Path $projectDirectory 'public/build/manifest.json'))) {
    & npm run build
    if ($LASTEXITCODE -ne 0) { throw 'Falha no build.' }
}
if (Test-Path -LiteralPath $statePath) {
    $previousState = Get-Content -Raw -LiteralPath $statePath | ConvertFrom-Json
    $running = @($previousState.processes | Where-Object { Get-Process -Id $_.id -ErrorAction SilentlyContinue })
    if ($running.Count -gt 0) { Write-Output "A plataforma já está iniciada em $($previousState.url). Use stop-local.ps1 para reiniciar."; exit 0 }
}
$probe = New-Object System.Net.Sockets.TcpClient
try { $probe.Connect('127.0.0.1', $Port); throw "A porta $Port já está ocupada." } catch [System.Net.Sockets.SocketException] { } finally { $probe.Dispose() }
$tasks = @(
    @{ name = 'server'; command = ('"{0}" serve --host=127.0.0.1 --port={1} --no-reload' -f $artisanPath, $Port) },
    @{ name = 'queue'; command = ('"{0}" queue:work --sleep=2 --tries=3 --timeout=660' -f $artisanPath) },
    @{ name = 'scheduler'; command = ('"{0}" schedule:work' -f $artisanPath) }
)
$processes = @()
foreach ($task in $tasks) {
    $process = Start-Process -FilePath $phpBinary -ArgumentList $task.command -WorkingDirectory $projectDirectory -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $logDirectory ($task.name+'.out.log')) -RedirectStandardError (Join-Path $logDirectory ($task.name+'.err.log'))
    $processes += @{ name = $task.name; id = $process.Id; started = $process.StartTime.ToUniversalTime().ToString('o') }
}
@{ url = "http://127.0.0.1:$Port"; processes = $processes } | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath $statePath -Encoding UTF8
$ready = $false
for ($attempt = 0; $attempt -lt 20; $attempt++) {
    try { $response = Invoke-WebRequest -UseBasicParsing -Uri "http://127.0.0.1:$Port/up" -TimeoutSec 2; if ($response.StatusCode -eq 200) { $ready = $true; break } } catch { }
    Start-Sleep -Milliseconds 300
}
if (-not $ready) { throw 'O servidor não respondeu. Consulte storage/logs/server.err.log.' }
Write-Output "Website: http://127.0.0.1:$Port"
Write-Output "Administração: http://127.0.0.1:$Port/login"
Write-Output 'Servidor, fila e agendador estão em execução. Para parar: .\stop-local.ps1'
