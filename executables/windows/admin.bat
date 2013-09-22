@echo off

REM change window title
TITLE Admin Control Panel - websocket server

REM change window background and color
Color 0A

REM start the server
php ../../core/wrapper.php -server:admin -admin -verbose -debug -warn