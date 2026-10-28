@echo off
chcp 65001 >nul
cd /d "%~dp0"
where node >nul 2>&1
if errorlevel 1 (
  echo Node.js 22 of nieuwer is nodig. Download Node.js van https://nodejs.org/
  pause
  exit /b 1
)
if not exist .env (
  copy .env.example .env >nul
  echo Het bestand .env is aangemaakt. Vul ADMIN_PASSWORD later in voor crewbeheer.
)
echo.
echo DCTerra 24 Uur Live website start...
echo Homepage: http://localhost:3000
echo Crew:     http://localhost:3000/crew
echo OBS:      http://localhost:3000/overlay
echo.
start "" cmd /c "timeout /t 2 >nul & start http://localhost:3000"
node server.mjs
pause
