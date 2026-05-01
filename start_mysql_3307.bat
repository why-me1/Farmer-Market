@echo off
setlocal

set "MYSQLD=C:\xampp\mysql\bin\mysqld.exe"
set "DATADIR=C:\xampp\mysql\data"

if not exist "%MYSQLD%" (
    echo Could not find %MYSQLD%
    exit /b 1
)

"%MYSQLD%" --console --port=3306 --datadir="%DATADIR%"
