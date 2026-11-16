$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$runtimePath = Join-Path $projectRoot '.runtime'
New-Item -ItemType Directory -Path $runtimePath -Force | Out-Null
foreach ($port in @(8017,5177)) {
    if (Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue) {
        throw "Port $port is already occupied. Stop the previous process before starting."
    }
}
$phpPath = Join-Path $projectRoot '.tools/php/php.exe'
$nodePath = (Get-Command node.exe).Source
$vitePath = Join-Path $projectRoot 'frontend/node_modules/vite/bin/vite.js'
$backendProcess = Start-Process -FilePath $phpPath -ArgumentList @('artisan','serve','--host=127.0.0.1','--port=8017','--no-reload') -WorkingDirectory (Join-Path $projectRoot 'backend') -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtimePath 'backend.log') -RedirectStandardError (Join-Path $runtimePath 'backend-error.log')
try {
    $frontendProcess = Start-Process -FilePath $nodePath -ArgumentList @($vitePath) -WorkingDirectory (Join-Path $projectRoot 'frontend') -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtimePath 'frontend.log') -RedirectStandardError (Join-Path $runtimePath 'frontend-error.log')
} catch {
    taskkill.exe /PID $backendProcess.Id /T /F | Out-Null
    throw
}
@{backend=$backendProcess.Id;frontend=$frontendProcess.Id} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $runtimePath 'processes.json')
Write-Output 'Frontend: http://127.0.0.1:5177'
Write-Output 'Backend: http://127.0.0.1:8017/api/health'
