@echo off

REM change window title
TITLE Game - RPG - websocket server

REM change window background and color
Color 0A

REM start the server
php ../../core/wrapper.php -server:game-rpg -verbose -debug -warn -admin