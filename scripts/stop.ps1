$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$pidFile = Join-Path $projectRoot '.runtime/processes.json'
if (!(Test-Path -LiteralPath $pidFile)) { Write-Output 'No recorded processes.'; exit 0 }
$recorded = Get-Content -LiteralPath $pidFile -Raw | ConvertFrom-Json
foreach ($processId in @($recorded.backend,$recorded.frontend)) {
    $processInfo = Get-CimInstance Win32_Process -Filter "ProcessId = $processId" -ErrorAction SilentlyContinue
    if ($processInfo -and ($processInfo.CommandLine.Contains($projectRoot) -or $processInfo.ExecutablePath -eq (Join-Path $projectRoot '.tools/php/php.exe'))) {
        taskkill.exe /PID $processId /T /F
    }
}
Remove-Item -LiteralPath $pidFile
