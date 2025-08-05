@echo off
echo 酷瓜云课堂项目启动脚本
echo ========================

REM 设置PHP 7.4路径
set PHP_PATH=E:\PHP74
set COMPOSER_PATH=C:\ProgramData\ComposerSetup\bin\composer.bat

echo 检查PHP 7.4...
if not exist "%PHP_PATH%\php.exe" (
    echo 错误：PHP 7.4未找到，请先安装PHP 7.4到 %PHP_PATH%
    pause
    exit /b 1
)

echo 检查Composer...
if not exist "%COMPOSER_PATH%" (
    echo 错误：Composer未找到
    pause
    exit /b 1
)

echo 设置环境变量...
set PATH=%PHP_PATH%;%PATH%

echo 检查PHP版本...
%PHP_PATH%\php.exe -v

echo 检查PHP扩展...
%PHP_PATH%\php.exe -m | findstr -i "phalcon redis curl gd mbstring mysqli pdo"

echo 安装项目依赖...
%COMPOSER_PATH% install --ignore-platform-req=ext-phalcon --ignore-platform-req=ext-redis

echo 启动内置服务器...
echo 访问地址：http://127.0.0.1:8000
echo 后台地址：http://127.0.0.1:8000/admin
echo 按 Ctrl+C 停止服务器

%PHP_PATH%\php.exe -S 127.0.0.1:8000 -t public

pause 