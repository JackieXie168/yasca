@echo off
cd resources\utility\pixy
set mypath=%~dp0
java -Xmx500m -Xms500m -Dpixy.home="%mypath%\" -jar pixy.jar -a -A -y xss:file:sql %*
del graphs\*.dot
del graphs\*.txt
