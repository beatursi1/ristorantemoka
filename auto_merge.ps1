$owner = "beatursi1"
$repo = "ristorantemoka"
$pr = 15
$maxAttempts = 180
$interval = 10

Write-Host "Starting polling for PR #$pr (repo $owner/$repo). Max attempts: $maxAttempts, interval: $interval s"

for ($i = 1; $i -le $maxAttempts; $i++) {
    Write-Host "Attempt $i/$maxAttempts"
    $sha = gh api repos/$owner/$repo/pulls/$pr -q .head.sha 2>$null
    if (-not $sha) {
        Write-Host "Could not get head SHA, retrying in $interval s..."
        Start-Sleep -Seconds $interval
        continue
    }
    $state = gh api repos/$owner/$repo/commits/$sha/status -q .state 2>$null
    Write-Host "Combined state for $sha: $state"
    if ($state -eq "success") {
        Write-Host "Checks passed. Trying to merge PR #$pr (rebase)..."
        gh pr merge $pr --repo $owner/$repo --rebase --delete-branch --body "Merge pull request #$pr: fix(menu): remove duplicate window._menu_macros assignment"
        if ($LASTEXITCODE -eq 0) { Write-Host "Merge (rebase) succeeded."; exit 0 }
        Write-Host "Rebase merge failed (exit $LASTEXITCODE). Trying squash..."
        gh pr merge $pr --repo $owner/$repo --squash --delete-branch --body "Merge pull request #$pr: fix(menu): remove duplicate window._menu_macros assignment"
        if ($LASTEXITCODE -eq 0) { Write-Host "Merge (squash) succeeded."; exit 0 }
        Write-Host "Merge attempts failed. Exiting."
        exit 2
    }
    if ($state -eq "failure" -or $state -eq "error") {
        Write-Host "Checks failed (state=$state). Aborting. See PR: https://github.com/$owner/$repo/pull/$pr"
        exit 3
    }
    Write-Host "Checks pending. Waiting $interval s..."
    Start-Sleep -Seconds $interval
}

Write-Host "Timeout reached ($maxAttempts attempts). Please check PR: https://github.com/$owner/$repo/pull/$pr"
exit 4
