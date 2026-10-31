CREATE DATABASE IF NOT EXISTS universite CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE universite;

CREATE TABLE IF NOT EXISTS etudiant (
  idEtu VARCHAR(20) PRIMARY KEY,
  nomEtu VARCHAR(100) NOT NULL,
  prenomEtu VARCHAR(100) NOT NULL,
  sexeEtu ENUM('Masculin', 'Féminin', 'Autre') NOT NULL,
  dateNaiss DATE NOT NULL,
  lieuNaiss VARCHAR(150) NOT NULL,
  emailEtu VARCHAR(150) NOT NULL UNIQUE,
  numTelEtu VARCHAR(30) NOT NULL,
  regEtu VARCHAR(100) NOT NULL,
  departEtu VARCHAR(100) NOT NULL,
  arrondisEtu VARCHAR(100) NOT NULL,
  mentionEtu VARCHAR(100) NOT NULL,
  cycleEtu VARCHAR(50) NOT NULL,
  ParcoursEtu VARCHAR(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
