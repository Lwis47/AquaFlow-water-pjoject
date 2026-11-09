@echo off
rem Local dev server with the SQLite PDO driver enabled (no admin rights needed).
cd /d "%~dp0"
php -d extension=pdo_sqlite -d extension=sqlite3 -S localhost:8000 -t .