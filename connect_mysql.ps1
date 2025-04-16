# PowerShell script to connect to MySQL and keep trying until it succeeds
$mysqlPath = "C:\Program Files\MySQL\MySQL Server 9.0\bin\mysql.exe"
$username = "root"
$password = "Goodluck@6842"
$maxAttempts = 10
$attempt = 0
$connected = $false

Write-Host "MySQL path: $mysqlPath" -ForegroundColor Yellow
Write-Host "Checking if MySQL executable exists..." -ForegroundColor Yellow

if (Test-Path $mysqlPath) {
    Write-Host "MySQL executable found at: $mysqlPath" -ForegroundColor Green
} else {
    Write-Host "MySQL executable not found at: $mysqlPath" -ForegroundColor Red
    Write-Host "Please check the path and update the script." -ForegroundColor Red
    exit
}

Write-Host "Attempting to connect to MySQL..." -ForegroundColor Yellow

while (-not $connected -and $attempt -lt $maxAttempts) {
    $attempt++
    Write-Host "Attempt $attempt of $maxAttempts..." -ForegroundColor Cyan
    
    try {
        # Create a temporary file with the SQL command
        $tempFile = [System.IO.Path]::GetTempFileName()
        "SELECT VERSION();" | Out-File -FilePath $tempFile -Encoding ascii
        
        Write-Host "Executing MySQL command..." -ForegroundColor Yellow
        $result = & $mysqlPath -u $username -p$password --execute="source $tempFile" 2>&1
        
        # Clean up the temporary file
        Remove-Item -Path $tempFile -Force
        
        if ($LASTEXITCODE -eq 0) {
            $connected = $true
            Write-Host "Successfully connected to MySQL!" -ForegroundColor Green
            Write-Host "MySQL Version: $result" -ForegroundColor Green
            
            # Now execute the SQL scripts
            Write-Host "Executing Database Creation script..." -ForegroundColor Yellow
            & $mysqlPath -u $username -p$password --execute="source `"$PWD\Database Creation (1).sql`""
            
            Write-Host "Executing RBAC script..." -ForegroundColor Yellow
            & $mysqlPath -u $username -p$password --execute="source `"$PWD\RBAC (1).sql`""
            
            Write-Host "All scripts executed successfully!" -ForegroundColor Green
        } else {
            Write-Host "Failed to connect. Error: $result" -ForegroundColor Red
            Write-Host "Waiting 5 seconds before trying again..." -ForegroundColor Yellow
            Start-Sleep -Seconds 5
        }
    } catch {
        Write-Host "Exception occurred: $_" -ForegroundColor Red
        Write-Host "Waiting 5 seconds before trying again..." -ForegroundColor Yellow
        Start-Sleep -Seconds 5
    }
}

if (-not $connected) {
    Write-Host "Failed to connect to MySQL after $maxAttempts attempts." -ForegroundColor Red
    Write-Host "Please check your MySQL installation and credentials." -ForegroundColor Red
} 