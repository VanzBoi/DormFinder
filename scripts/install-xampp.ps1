param([string]$Destination = 'C:\xampp\htdocs\DORMFINDER')
$ErrorActionPreference = 'Stop'
$sourceRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
$destinationRoot = [IO.Path]::GetFullPath($Destination)
if ($destinationRoot -eq $sourceRoot -or $destinationRoot.StartsWith($sourceRoot + '\', [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Choose an installation folder outside the source project.'
}
if ((Test-Path -LiteralPath $destinationRoot) -and (Get-ChildItem -LiteralPath $destinationRoot -Force | Select-Object -First 1)) {
    throw 'The destination is not empty. No files were overwritten. Open the existing installation or choose a new destination.'
}
New-Item -ItemType Directory -Path $destinationRoot -Force | Out-Null
$files = Get-ChildItem -LiteralPath $sourceRoot -File -Recurse -Force | Where-Object {
    $relative = $_.FullName.Substring($sourceRoot.Length + 1)
    $relative -notmatch '^(tests\\\.runtime|\.git|\.codex|\.agents)(\\|$)' -and $relative -ne 'config\local.php'
}
foreach ($file in $files) {
    $relative = $file.FullName.Substring($sourceRoot.Length + 1)
    $target = Join-Path $destinationRoot $relative
    New-Item -ItemType Directory -Path (Split-Path -Parent $target) -Force | Out-Null
    Copy-Item -LiteralPath $file.FullName -Destination $target
}
Write-Output "Installed $($files.Count) files in $destinationRoot"
Write-Output 'Start Apache and MySQL, import database/dormfinder.sql if this is a fresh database, then open http://localhost/DORMFINDER/.'
