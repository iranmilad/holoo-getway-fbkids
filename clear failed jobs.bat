@echo off
title API SERVER System
echo Wait for start daily service.

echo Web Service run
echo Dont Close This windows

cmd /k "cd /d D:\xampp\htdocs\holoo-getway-perestashop & php artisan queue:flush"
