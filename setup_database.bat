@echo off
mysql -u root -D sistema_frotas < sql/create_motoristas.sql

echo Database tables created successfully!
pause 