$owner="beatursi1"
$repo="ristorantemoka"
$pr=15
$maxAttempts=180
$interval=10
Write-Host ("Starting polling for PR #{0} (repo {1}/{2}). Max attempts: {3}, interval: {4}s" -f $pr,$owner,$repo,$maxAttempts,$interval)
for ($i=1; $i -le $maxAttempts; $i++) {
    Write-Host ("Attempt {0}/{1}" -f $i,$maxAttempts)
    $sha = gh api repos/$owner/$repo/pulls/$pr -q .head.sha 2>$null
    if (-not $sha) { Write-Host ("Could not get head SHA, retrying in {0}s..." -f $interval); Start-Sleep -Seconds $interval; continue }
    $state = gh api repos/$owner/$repo/commits/$sha/status -q .state 2>$null
    Write-Host ("Combined state for {0}: {1}" -f $sha,$state)
    if ($state -eq "success") {
        Write-Host ("Checks passed. Trying to merge PR #{0} (rebase)..." -f $pr)
        gh pr merge $pr --repo $owner/$repo --rebase --delete-branch --body ("Merge pull request #{0}: fix(menu): remove duplicate window._menu_macros assignment" -f $pr)
        if ($LASTEXITCODE -eq 0) { Write-Host "Merge (rebase) succeeded."; exit 0 }
        Write-Host ("Rebase merge failed (exit {0}). Trying squash..." -f $LASTEXITCODE)
        gh pr merge $pr --repo $owner/$repo --squash --delete-branch --body ("Merge pull request #{0}: fix(menu): remove duplicate window._menu_macros assignment" -f $pr)
        if ($LASTEXITCODE -eq 0) { Write-Host "Merge (squash) succeeded."; exit 0 }
        Write-Host ("Merge attempts failed (exit {0}). Exiting." -f $LASTEXITCODE); exit 2
    }
    if ($state -eq "failure" -or $state -eq "error") { Write-Host ("Checks failed (state={0}). Aborting. See PR: https://github.com/{1}/{2}/pull/{3}" -f $state,$owner,$repo,$pr); exit 3 }
    Write-Host ("Checks pending. Waiting {0}s..." -f $interval); Start-Sleep -Seconds $interval
}
Write-Host ("Timeout reached ({0} attempts). Please check PR: https://github.com/{1}/{2}/pull/{3}" -f $maxAttempts,$owner,$repo,$pr)
exit 4
