@echo off
set TOPDIR=%1
set VERSION=4.1
set PMDJAR=%TOPDIR%/pmd14-%VERSION%.jar
set JARPATH=%TOPDIR%/asm-3.1.jar;%TOPDIR%/jaxen-1.1.1.jar
set RWPATH=%TOPDIR%/retroweaver-rt-2.0.2.jar;%TOPDIR%/backport-util-concurrent.jar
set JARPATH=%JARPATH%;%RWPATH%
set OPTS=
set MAIN_CLASS=net.sourceforge.pmd.PMD

java %OPTS% -cp "%PMDJAR%;%JARPATH%;" %MAIN_CLASS% %2 %3 %4 %5 %6 %7 %8

