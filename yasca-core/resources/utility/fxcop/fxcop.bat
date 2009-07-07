@echo off

rem SET THIS TO A DIFFERENT PATH TO USE A DIFFERENT VERSION OF FXCOP
set FXCOP_PATH=%ProgramFiles%\Microsoft FxCop 1.36

if not exist "%FXCOP_PATH%" goto notfound

if "%~1"=="" goto nodir
set TDEST=%~dp0
set TGT=%~1


cd "%FXCOP_PATH%"


"%FXCOP_PATH%\FxCopCmd.exe" "/out:%TDEST%\scan.xml" "/rule:%FXCOP_PATH%\Rules\SecurityRules.dll" "/rule:%FXCOP_PATH%\Rules\DesignRules.dll" /iit "/file:%TGT%" 2>&1 > NUL

goto end
:notfound
echo Cannot find Microsoft FxCop. Please install from http://code.msdn.microsoft.com/codeanalysis or change FXCOP_PATH in resources\utility\fxcop\fxcop.bat
goto enderror

goto end
:nodir
echo No file or directory passed to fxcop.bat. Nothing to scan.
goto enderror

:end
cd %TDEST%
type scan.xml
erase scan.xml
goto realend

:enderror
echo Error

:realend