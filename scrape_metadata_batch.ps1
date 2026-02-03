$urls = Get-Content urls.txt
$config = "C:\Users\coldh\.gemini\antigravity\scratch\gallery-dl.conf"
$outDir = "metadata_dump"
New-Item -ItemType Directory -Force -Path $outDir | Out-Null

$count = 0
foreach ($url in $urls) {
    if ([string]::IsNullOrWhiteSpace($url)) { continue }
    
    # Extract ID from URL (last part after hyphen)
    $hash = $url.Split('-')[-1]
    $outfile = "$outDir\${hash}.json"
    
    # Skip if already exists AND has content
    if ((Test-Path $outfile) -and ((Get-Item $outfile).Length -gt 10)) { 
        Write-Host "Skipping $hash (exists)"
        continue 
    }
    
    Write-Host "[$count] Fetching $url ..."
    
    # Use cmd redirection for reliable output capture
    $result = & gallery-dl --config $config --dump-json $url 2>&1
    
    # Filter only JSON lines (starts with [ or {)
    $jsonLines = $result | Where-Object { $_ -match '^\s*[\[\{]' }
    
    if ($jsonLines) {
        $jsonLines | Out-File -FilePath $outfile -Encoding UTF8
        Write-Host "  -> Saved to $outfile"
    }
    else {
        Write-Host "  -> No JSON output (rate limited or error)"
        # Write the raw output for debugging
        $result | Out-File -FilePath "$outDir\${hash}_debug.txt" -Encoding UTF8
    }
    
    # Delay between requests (gallery-dl handles internal delays, this is extra politeness)
    Start-Sleep -Seconds 2
    $count++
}
Write-Host "Batch complete. Processed $count items."
