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

# Costruiamo le sequenze corrotte con escape numerici (evitiamo caratteri non-ASCII nel file script)
$corrupt1 = [string]([char]0xE2 + [char]0x82 + [char]0xAC)   # 'â‚¬'
$corrupt2 = [string]([char]0xD4 + [char]0xE9 + [char]0xBC)   # 'Ôé¼'
$corrupt3 = [string]([char]0xC2 + [char]0x80)                # possibile variante 'Â€'

# Sostituiamo con il carattere euro Unicode
$euro = [char]0x20AC
$content = $content.Replace($corrupt1, $euro).Replace($corrupt2, $euro).Replace($corrupt3, $euro)

# Assicura una sola newline finale
$content = $content.TrimEnd("`r","`n") + "`n"

# Scrivi in UTF8 senza BOM
[System.IO.File]::WriteAllText($path, $content, (New-Object System.Text.UTF8Encoding($false)))

Write-Output "OK: file corretto e salvato come UTF8 senza BOM. Backup -> $bak"
