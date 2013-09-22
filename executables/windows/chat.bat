@echo off

REM change window title
TITLE Simple Chat - websocket server

REM change window background and color
Color 0A

REM start the server
php ../../core/wrapper.php -server:chat -verbose -debug -warn