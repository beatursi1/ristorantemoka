Param(
  [string]$Message = "chore: update",
  [string]$Branch = "",
  [string]$Title = "",
  [string]$Body = "",
  [string]$Base = "main",
  [switch]$NoPr,
  [switch]$OpenBrowser,
  [switch]$AutoMerge,
  [ValidateSet("rebase","squash","merge")]
  [string]$MergeMethod = "rebase",
  [switch]$AutoApprove,
  [string]$PrePushCmd = "",
  [int]$WaitTimeoutSeconds = 1800,   # 30 min default
  [int]$PollIntervalSeconds = 15,
  [string]$LogFile = ""
)

function Log {
  param($msg)
  $ts = (Get-Date).ToString("s")
  $line = "[$ts] $msg"
  Write-Host $line
  if (-not [string]::IsNullOrEmpty($LogFile)) {
    $line | Out-File -FilePath $LogFile -Append -Encoding utf8
  }
}

function ExitWithError($msg) {
  Log "ERROR: $msg"
  exit 1
}

# prerequisiti
if (-not (Get-Command git -ErrorAction SilentlyContinue)) { ExitWithError "git non trovato. Installalo e riprova." }
if (-not (Get-Command gh -ErrorAction SilentlyContinue)) { ExitWithError "gh CLI non trovato. Installalo e autenticati (gh auth login)." }

# Pre-push command (se impostato)
if (-not [string]::IsNullOrEmpty($PrePushCmd)) {
  Log "Eseguo comando pre-push: $PrePushCmd"
  Invoke-Expression $PrePushCmd
  if ($LASTEXITCODE -ne 0) {
    ExitWithError "Comando pre-push fallito con codice $LASTEXITCODE. Interrompo."
  } else {
    Log "Comando pre-push completato con successo."
  }
}

# ricava branch corrente
$curBranch = (& git rev-parse --abbrev-ref HEAD 2>$null).Trim()
if (-not $curBranch) { ExitWithError "Impossibile determinare il branch corrente (forse HEAD staccata)." }

# se l'utente non ha passato Branch, usa quello corrente; se è main/master, crea una branch nuova
if ([string]::IsNullOrEmpty($Branch)) {
  if ($curBranch -in @("main","master")) {
    $ts = Get-Date -Format "yyyyMMdd-HHmmss"
    $Branch = "feat/auto-$ts"
    Log "Sei su '$curBranch' — creo e passo a nuova branch '$Branch'."
    & git checkout -b $Branch
    if ($LASTEXITCODE -ne 0) { ExitWithError "git checkout -b $Branch fallito." }
  } else {
    $Branch = $curBranch
    Log "Lavorerò sulla branch corrente: $Branch"
  }
} else {
  if ($curBranch -ne $Branch) {
    Log "Passo al branch $Branch (creo se necessario)."
    & git checkout -b $Branch 2>$null
    if ($LASTEXITCODE -ne 0) {
      # potrebbe esistere già
      & git checkout $Branch
      if ($LASTEXITCODE -ne 0) { ExitWithError "Impossibile passare/creare branch $Branch." }
    }
  }
}

# Aggiungi tutte le modifiche
Log "git add -A"
& git add -A
if ($LASTEXITCODE -ne 0) { ExitWithError "git add fallito." }

# Controlla se ci sono modifiche da commitare
$porcelain = (& git status --porcelain)
if (-not [string]::IsNullOrEmpty($porcelain)) {
  Log "Modifiche rilevate, eseguo commit con messaggio: $Message"
  & git commit -m $Message
  if ($LASTEXITCODE -ne 0) {
    Log "git commit ha restituito codice $LASTEXITCODE. Potrebbe non esserci nulla da commitare o hook che bloccano."
  } else {
    Log "Commit eseguito."
  }
} else {
  Log "Nessuna modifica da commitare."
}

# Push: controlla se esiste upstream
$hasUpstream = $true
& git rev-parse --abbrev-ref --symbolic-full-name @{u} 2>$null
if ($LASTEXITCODE -ne 0) { $hasUpstream = $false }

if ($hasUpstream) {
  Log "git push"
  & git push
  if ($LASTEXITCODE -ne 0) { ExitWithError "git push fallito." }
} else {
  Log "git push --set-upstream origin $Branch"
  & git push --set-upstream origin $Branch
  if ($LASTEXITCODE -ne 0) { ExitWithError "git push --set-upstream fallito." }
}

# Se NoPr, esci qui
if ($NoPr) {
  Log "Skip creazione PR (--NoPr)."
  exit 0
}

# ricava repo full name (owner/repo)
$repo = (& gh repo view --json nameWithOwner --jq '.nameWithOwner' 2>$null).Trim()
if (-not $repo) { ExitWithError "Impossibile determinare repository remoto con 'gh repo view'." }

# Verifica se esiste già una PR per la branch $Branch ...
Log "Verifico se esiste già una PR per la branch $Branch ..."
$prRaw = (& gh pr view --head $Branch --repo $repo --json number,headRefOid,mergeable 2>$null) 2>$null
if ($LASTEXITCODE -eq 0 -and -not [string]::IsNullOrEmpty($prRaw)) {
  try {
    $pr = $prRaw | ConvertFrom-Json
  } catch {
    $pr = $null
  }
} else {
  $pr = $null
}

if ($pr -ne $null -and $pr.number) {
  $prNumber = $pr.number
  Log "PR già esistente: number #$prNumber"
  $prHeadOid = $pr.headRefOid
  if ($OpenBrowser) {
    try {
      $url = (& gh pr view $prNumber --repo $repo --json url --jq '.url').Trim()
      if ($url) {
        Log "Apro la PR nel browser..."
        Start-Process $url
      }
    } catch {
      Log "Impossibile aprire il browser automaticamente. URL PR: $url"
    }
  }
} else {
  # crea PR usando la base scelta
  $prTitle = if (-not [string]::IsNullOrEmpty($Title)) { $Title } else { $Message }
  $prBody  = if (-not [string]::IsNullOrEmpty($Body))  { $Body }  else { "" }

  Log "Creo nuova PR (branch: $Branch -> base: $Base) con titolo: $prTitle"
  & gh pr create --title $prTitle --body $prBody --base $Base --head $Branch --repo $repo
  if ($LASTEXITCODE -ne 0) { ExitWithError "Creazione PR fallita. Controlla l'output sopra." }

  # recupera PR info
  $prRaw = (& gh pr view --head $Branch --repo $repo --json number,headRefOid,mergeable)
  if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrEmpty($prRaw)) { ExitWithError "Non riesco a leggere la PR appena creata." }
  $pr = $prRaw | ConvertFrom-Json
  $prNumber = $pr.number
  $prHeadOid = $pr.headRefOid
  Log "PR creata: #$prNumber"
  if ($OpenBrowser) {
    try {
      $url = (& gh pr view $prNumber --repo $repo --json url --jq '.url').Trim()
      if ($url) {
        Log "Apro la PR nel browser..."
        Start-Process $url
      }
    } catch {
      Log "Impossibile aprire il browser automaticamente. URL PR: $url"
    }
  }
}

# Se AutoMerge abilitato, attendi i checks e fai merge automatico
if ($AutoMerge) {
  if (-not $prNumber -or -not $prHeadOid) { ExitWithError "Informazioni PR mancanti, impossibile procedere con AutoMerge." }
  Log "AutoMerge attivato: attendo che i check sul commit $prHeadOid diventino 'success' (timeout $WaitTimeoutSeconds s)."

  $start = Get-Date
  while ($true) {
    # ottieni stato dei check combinato per il commit
    $state = (& gh api -X GET "/repos/$repo/commits/$prHeadOid/status" --jq '.state' 2>$null).Trim()
    if (-not $state) { $state = "pending" }
    Log "Stato corrente checks: $state"

    if ($state -eq "success") {
      Log "Checks verdi: procedo."
      break
    } elseif ($state -eq "failure" -or $state -eq "error") {
      ExitWithError "Checks falliti (state=$state). Non effettuo merge automatico."
    } else {
      # pending o altro -> controlla timeout
      $elapsed = (Get-Date) - $start
      if ($elapsed.TotalSeconds -ge $WaitTimeoutSeconds) {
        ExitWithError "Timeout ($WaitTimeoutSeconds s) raggiunto aspettando i checks. Interrompo."
      }
      Start-Sleep -Seconds $PollIntervalSeconds
    }
  }

  # (opzionale) auto-approve
  if ($AutoApprove) {
    Log "Eseguo approvazione automatica della PR #$prNumber"
    & gh pr review $prNumber --repo $repo --approve --body "Auto-approvata dallo script"
    if ($LASTEXITCODE -ne 0) { Log "Attenzione: auto-approve fallito o non permesso." }
  }

  # controlla se è mergeabile (best-effort)
  if ($pr.mergeable) {
    Log "PR mergeable: $($pr.mergeable)"
  } else {
    Log "Attenzione: stato 'mergeable' non determinato o conflitti possibili. Continuerò comunque e lascerò fallire il merge se necessario."
  }

  # effettua merge (skip prompt con --confirm)
  Log "Eseguo merge della PR #$prNumber con metodo '$MergeMethod' e cancello la branch remota."
  switch ($MergeMethod) {
    "rebase" { & gh pr merge $prNumber --repo $repo --rebase --delete-branch --confirm }
    "squash" { & gh pr merge $prNumber --repo $repo --squash --delete-branch --confirm }
    default  { & gh pr merge $prNumber --repo $repo --merge --delete-branch --confirm }
  }
  if ($LASTEXITCODE -ne 0) { ExitWithError "Merge automatico fallito. Controlla i permessi e lo stato della PR." }
  Log "Merge eseguito con successo."
}

Log "Script completato."