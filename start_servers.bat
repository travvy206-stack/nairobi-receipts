@echo off
echo Starting Python Flask PDF Microservice on http://localhost:5000...
start "Python PDF Microservice" python service_pdf\app.py

echo Starting PHP Server on http://localhost:8000...
php -S localhost:8000 -t public
pause
