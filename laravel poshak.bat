@echo off
title API SERVER System
echo Please Check To IB Broker Program Was Open
echo Wait for start daily service.

echo Web Service run
echo Dont Close This windows

cmd /k "cd /d D:\xampp\htdocs\holooGetway & php artisan queue:work --queue=poshak --memory=1048"