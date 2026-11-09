#!/usr/bin/env sh
# Local dev server with the SQLite PDO driver enabled (no admin rights needed).
cd "$(dirname "$0")" || exit 1
exec php -d extension=pdo_sqlite -d extension=sqlite3 -S localhost:8000 -t .