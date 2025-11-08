param(
    [int]$Lines = 200,
    [string]$Contains,
    [switch]$Follow
)

$basePath = Split-Path -Parent $PSScriptRoot
$logPath = Join-Path $basePath "storage\logs\laravel.log"

try {
    if (!(Test-Path $logPath)) {
        throw "Log file not found at $logPath"
    }

    $tailParams = @{
        Path = $logPath
        Tail = $Lines
    }

    if ($Follow) {
        $tailParams['Wait'] = $true
    }

    $content = Get-Content @tailParams -ErrorAction Stop

    if ($Contains) {
        $content |
            Where-Object { $_ -match [regex]::Escape($Contains) } |
            ForEach-Object { Write-Output $_ }
    } else {
        $content | ForEach-Object { Write-Output $_ }
    }
} catch {
    Write-Error $_
    exit 1
}

