# Start Python Flask Document Microservice on Port 5000 in background
Write-Host "Starting Python Flask PDF Microservice on http://localhost:5000..." -ForegroundColor Cyan
$pythonProcess = Start-Process python -ArgumentList "service_pdf/app.py" -PassThru -NoNewWindow

# Start PHP Built-in Server on Port 8000
Write-Host "Starting PHP Server on http://localhost:8000..." -ForegroundColor Green
$env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")
php -S localhost:8000 -t public
