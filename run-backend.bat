@echo off
pushd "%~dp0"
echo Starting Laravel dev server on http://127.0.0.1:8001 ...
"C:\xampp\php\php.exe" artisan serve --host=127.0.0.1 --port=8001
popd
