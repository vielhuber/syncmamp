@echo off
REM save current dir
set OLDDIR=%CD%
REM change to script dir
@cd /d "%~dp0"
php syncmamp.php %*
REM change back
IF "%1" == "open" (
	chdir /d C:\MAMP\htdocs\%2
) ELSE (
	chdir /d %OLDDIR%
)