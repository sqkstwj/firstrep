@echo off
echo PHP版本切换工具
echo =================
echo 1. PHP 8.4 (E:\PHP)
echo 2. PHP 7.4 (E:\PHP74)
echo =================
set /p choice=请选择PHP版本 (1 或 2): 

if "%choice%"=="1" (
    set PATH=%PATH%;E:\PHP
    echo 已切换到 PHP 8.4
    E:\PHP\php.exe -v
) else if "%choice%"=="2" (
    set PATH=%PATH%;E:\PHP74
    echo 已切换到 PHP 7.4
    E:\PHP74\php.exe -v
) else (
    echo 无效选择
)
pause 