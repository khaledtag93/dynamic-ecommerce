@echo off
setlocal
set ROOT=%~dp0\..
set PROJECT_NAME=Tag-Marketplace
set OUTPUT=%1
if "%OUTPUT%"=="" set OUTPUT=Tag-Marketplace-dev.zip
powershell -NoProfile -Command "^
$root = Resolve-Path '%ROOT%'; ^
$tmp = Join-Path $env:TEMP ('tag_marketplace_' + [guid]::NewGuid().ToString()); ^
$project = Join-Path $tmp '%PROJECT_NAME%'; ^
New-Item -ItemType Directory -Force -Path $project | Out-Null; ^
robocopy $root $project /E /XD .git node_modules > $null; ^
if (Test-Path (Join-Path $root '%OUTPUT%')) { Remove-Item (Join-Path $root '%OUTPUT%') -Force }; ^
Compress-Archive -Path (Join-Path $tmp '%PROJECT_NAME%') -DestinationPath (Join-Path $root '%OUTPUT%'); ^
Remove-Item $tmp -Recurse -Force"
echo Created: %ROOT%\%OUTPUT%
endlocal
