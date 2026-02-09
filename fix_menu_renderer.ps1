$path = 'app/inizializza/js/menu-renderer.js'
if (-not (Test-Path $path)) {
    Write-Error "File non trovato: $path"
    exit 1
}

$bak = "$path.fixmenu.bak.$((Get-Date).ToString('yyyyMMddHHmmss'))"
Copy-Item $path $bak -Force

$content = [System.IO.File]::ReadAllText($path)

# Rimuovi BOM iniziale se presente
if ($content.Length -gt 0 -and $content[0] -eq [char]0xFEFF) {
    $content = $content.Substring(1)
}

# Sostituzioni robuste per i simboli euro corrotti (usiamo il codice Unicode)
$content = $content -replace 'â‚¬',([char]0x20AC) -replace 'Ôé¼',([char]0x20AC)

# Assicura una sola newline finale
$content = $content.TrimEnd("`r","`n") + "`n"

# Scrivi in UTF8 senza BOM
[System.IO.File]::WriteAllText($path, $content, (New-Object System.Text.UTF8Encoding($false)))

Write-Output "OK: file corretto e salvato come UTF8 senza BOM. Backup -> $bak"
