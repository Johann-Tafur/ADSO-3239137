-- ============================================
-- BASE DE DATOS - PRUEBA DE DESEMPEÑO
-- ============================================

CREATE DATABASE IF NOT EXISTS bd_prueba;
USE bd_prueba;

-- ============================================
-- DOMINIO UBICACIÓN
-- ============================================

CREATE TABLE continente (
    id_continente INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL
);

CREATE TABLE pais (
    id_pais INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    id_continente INT NOT NULL,
    FOREIGN KEY (id_continente) REFERENCES continente(id_continente)
);

CREATE TABLE departamento (
    id_departamento INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    id_pais INT NOT NULL,
    FOREIGN KEY (id_pais) REFERENCES pais(id_pais)
);

CREATE TABLE ciudad (
    id_ciudad INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    id_departamento INT NOT NULL,
    FOREIGN KEY (id_departamento) REFERENCES departamento(id_departamento)
);

CREATE TABLE barrio (
    id_barrio INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    id_ciudad INT NOT NULL,
    FOREIGN KEY (id_ciudad) REFERENCES ciudad(id_ciudad)
);

-- ============================================
-- DOMINIO SEGURIDAD Y ACCESO
-- ============================================

CREATE TABLE roles (
    id_rol INT PRIMARY KEY AUTO_INCREMENT,
    nombre_rol VARCHAR(50) NOT NULL,
    descripcion TEXT
);

CREATE TABLE persona (
    id_persona INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    telefono VARCHAR(20),
    id_barrio INT NOT NULL,
    FOREIGN KEY (id_barrio) REFERENCES barrio(id_barrio)
);

CREATE TABLE usuario (
    id_usuario INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    id_persona INT NOT NULL UNIQUE,
    id_rol INT NOT NULL,
    FOREIGN KEY (id_persona) REFERENCES persona(id_persona),
    FOREIGN KEY (id_rol) REFERENCES roles(id_rol)
);

-- ============================================
-- DOMINIO COMERCIAL
-- ============================================

CREATE TABLE categoria (
    id_categoria INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT
);

CREATE TABLE producto (
    id_producto INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    id_categoria INT NOT NULL,
    FOREIGN KEY (id_categoria) REFERENCES categoria(id_categoria)
);

CREATE TABLE factura (
    id_factura INT PRIMARY KEY AUTO_INCREMENT,
    fecha DATE NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    id_usuario INT NOT NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
);

CREATE TABLE detalle_factura (
    id_detalle INT PRIMARY KEY AUTO_INCREMENT,
    id_factura INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_factura) REFERENCES factura(id_factura),
    FOREIGN KEY (id_producto) REFERENCES producto(id_producto)
);