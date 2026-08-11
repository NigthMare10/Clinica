[CmdletBinding()]
param(
    [string] $PhpPath,
    [string] $HostAddress = '127.0.0.1',
    [ValidateRange(1, 65535)]
    [int] $Port = 8000,
    [switch] $Queue
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$phpIni = Join-Path $projectRoot '.runtime\php\php.ini'
$runtimeDirectories = @(
    (Join-Path $projectRoot 'storage\runtime\tmp'),
    (Join-Path $projectRoot 'storage\runtime\uploads'),
    (Join-Path $projectRoot 'storage\runtime\process')
)

foreach ($directory in $runtimeDirectories) {
    if (-not (Test-Path -LiteralPath $directory -PathType Container)) {
        New-Item -ItemType Directory -Path $directory | Out-Null
    }

    $probe = Join-Path $directory ".clinic-launcher-$([guid]::NewGuid().ToString('N'))"
    try {
        [System.IO.File]::WriteAllText($probe, 'clinic-runtime-ok')
    } finally {
        if (Test-Path -LiteralPath $probe) {
            Remove-Item -LiteralPath $probe -Force
        }
    }
}

if (-not (Test-Path -LiteralPath $phpIni -PathType Leaf)) {
    throw "Local PHP configuration not found: $phpIni"
}

$phpCandidates = @()
if ($PhpPath) {
    $phpCandidates += $PhpPath
} else {
    $phpCandidates += Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'
    $phpCandidates += @(Get-Command php -All -ErrorAction SilentlyContinue | ForEach-Object { $_.Source })
}

$php = $null
foreach ($candidate in @($phpCandidates | Select-Object -Unique)) {
    if (-not (Test-Path -LiteralPath $candidate -PathType Leaf)) {
        continue
    }

    & $candidate -n -r 'exit(PHP_VERSION_ID >= 80401 ? 0 : 1);'
    if ($LASTEXITCODE -eq 0) {
        $php = (Resolve-Path -LiteralPath $candidate).Path
        break
    }
}

if (-not $php) {
    throw 'PHP 8.4.1 or newer is required by the installed Composer dependencies. Pass its path with -PhpPath.'
}

$env:TEMP = $runtimeDirectories[0]
$env:TMP = $runtimeDirectories[0]
$env:TMPDIR = $runtimeDirectories[0]

Push-Location $projectRoot
$queueProcess = $null
try {
    & $php -c $phpIni artisan clinic:document-tools
    if ($LASTEXITCODE -ne 0) {
        throw "Document tool diagnosis failed with exit code $LASTEXITCODE."
    }

    & $php -c $phpIni artisan clinic:diagnose
    if ($LASTEXITCODE -ne 0) {
        throw "Runtime diagnosis failed with exit code $LASTEXITCODE."
    }

    if ($Queue) {
        $queueArguments = @('-c', "`"$phpIni`"", 'artisan', 'queue:work', '--tries=1', '--timeout=0')
        $queueProcess = Start-Process -FilePath $php -ArgumentList $queueArguments -NoNewWindow -PassThru
    }

    & $php -c $phpIni artisan serve "--host=$HostAddress" "--port=$Port"
} finally {
    if ($queueProcess -and -not $queueProcess.HasExited) {
        Stop-Process -Id $queueProcess.Id
    }
    Pop-Location
}
