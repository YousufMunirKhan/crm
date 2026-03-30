@echo off
echo Updating php-active symlink to PHP 8.4.16...
echo.
echo Please run Laragon as Administrator, then:
echo 1. Open Laragon
echo 2. Click Menu -^> PHP -^> Select "PHP 8.4.16-Win32-vs17-x64"
echo.
echo OR manually update the symlink (requires admin):
echo rmdir C:\laragon\bin\php\php-active
echo mklink /D C:\laragon\bin\php\php-active C:\laragon\bin\php\php-8.4.16-Win32-vs17-x64
echo.
pause

