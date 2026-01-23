@echo off

set SRC=C:\Users\Allan\Documents\project_talent\talent-agency-platform
set DEST=C:\xampp\htdocs\talent-agency-platform

REM Create destination if it doesn't exist
if not exist "%DEST%" mkdir "%DEST%"

REM Copy everything (including subfolders)
xcopy "%SRC%\*" "%DEST%\" /E /I /Y

echo Copy completed successfully.
pause
