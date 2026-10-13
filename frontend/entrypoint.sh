#!/bin/sh
set -e
cd /app

if [ ! -d node_modules/vue ]; then
  npm install
fi

exec npm run dev -- --host 0.0.0.0 --port 5173
