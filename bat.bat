@echo off
set BASE=talent-agency-platform

REM Create main directories
mkdir %BASE%\config
mkdir %BASE%\includes
mkdir %BASE%\classes
mkdir %BASE%\api
mkdir %BASE%\database

mkdir %BASE%\public\talent
mkdir %BASE%\public\employer
mkdir %BASE%\public\admin
mkdir %BASE%\public\assets\css
mkdir %BASE%\public\assets\js
mkdir %BASE%\public\assets\images
mkdir %BASE%\public\assets\vendor

mkdir %BASE%\uploads\profiles
mkdir %BASE%\uploads\resumes
mkdir %BASE%\uploads\portfolios
mkdir %BASE%\uploads\company-logos
mkdir %BASE%\uploads\documents

REM Config files
type nul > %BASE%\config\database.php
type nul > %BASE%\config\config.php
type nul > %BASE%\config\constants.php

REM Include files
type nul > %BASE%\includes\header.php
type nul > %BASE%\includes\footer.php
type nul > %BASE%\includes\navbar.php
type nul > %BASE%\includes\sidebar.php
type nul > %BASE%\includes\auth_check.php
type nul > %BASE%\includes\functions.php

REM Class files
for %%F in (
Database Auth User Talent Employer Job Application Contract Payment Message
Review Notification Skill Validator Upload Mail
) do (
    type nul > %BASE%\classes\%%F.php
)

REM .htaccess files (Windows won't enforce perms, but files are created)
type nul > %BASE%\uploads\profiles\.htaccess
type nul > %BASE%\uploads\resumes\.htaccess
type nul > %BASE%\uploads\portfolios\.htaccess
type nul > %BASE%\uploads\company-logos\.htaccess
type nul > %BASE%\uploads\documents\.htaccess

REM Root files
type nul > %BASE%\.env
type nul > %BASE%\.env.example
type nul > %BASE%\.gitignore
type nul > %BASE%\composer.json
type nul > %BASE%\README.md

echo Project structure created successfully!
pause
