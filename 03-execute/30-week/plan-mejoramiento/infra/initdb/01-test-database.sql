-- Base aparte para las pruebas de integración de api (tests/Integration).
-- PostgreSQL corre los archivos de esta carpeta UNA sola vez: cuando crea el volumen por primera vez.
-- Queda vacía, como la principal: el esquema lo crean las migraciones (artículo V).
CREATE DATABASE robotics_test;
