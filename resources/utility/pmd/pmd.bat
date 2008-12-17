@echo off
set TOPDIR=resources\utility\pmd
set VERSION=4.2.4
set PMDJAR=%TOPDIR%/PMD-Yasca.jar;%TOPDIR%/pmd14-%VERSION%.jar
set JARPATH=%TOPDIR%/asm-3.1.jar;%TOPDIR%/jaxen-1.1.1.jar;%TOPDIR%/YascaPMD.jar
set RWPATH=%TOPDIR%/retroweaver-rt-2.0.5.jar;%TOPDIR%/backport-util-concurrent.jar
set JARPATH=%JARPATH%;%RWPATH%
set OPTS=
set MAIN_CLASS=net.sourceforge.pmd.PMD

java %OPTS% -cp "%PMDJAR%;%JARPATH%;" %MAIN_CLASS% %1 net.sourceforge.pmd.renderers.YascaRenderer %2 %3 %4 %5 %6 %7 %8

