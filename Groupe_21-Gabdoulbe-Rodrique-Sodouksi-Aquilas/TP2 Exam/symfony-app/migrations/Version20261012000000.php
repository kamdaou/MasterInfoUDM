<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the order-management schema from the TP2 brief.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE Departement (idDep INT AUTO_INCREMENT NOT NULL, nomDep VARCHAR(150) NOT NULL, PRIMARY KEY(idDep)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE Region (idRegion INT AUTO_INCREMENT NOT NULL, nomRegion VARCHAR(150) NOT NULL, idDep INT NOT NULL, INDEX IDX_REGION_DEPARTEMENT (idDep), PRIMARY KEY(idRegion), CONSTRAINT FK_REGION_DEPARTEMENT FOREIGN KEY (idDep) REFERENCES Departement (idDep)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE Client (idCli INT AUTO_INCREMENT NOT NULL, nomCli VARCHAR(150) NOT NULL, prenomCli VARCHAR(150) NOT NULL, email VARCHAR(190) DEFAULT NULL, photo VARCHAR(255) DEFAULT NULL, idRegion INT DEFAULT NULL, INDEX IDX_CLIENT_REGION (idRegion), PRIMARY KEY(idCli), CONSTRAINT FK_CLIENT_REGION FOREIGN KEY (idRegion) REFERENCES Region (idRegion) ON DELETE SET NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE Produit (idProd INT AUTO_INCREMENT NOT NULL, designProd VARCHAR(255) NOT NULL, qteStock INT NOT NULL, prixUProd DECIMAL(12, 2) NOT NULL, photoProd VARCHAR(255) DEFAULT NULL, PRIMARY KEY(idProd)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE Commande (idCmd INT AUTO_INCREMENT NOT NULL, dateCmd DATETIME NOT NULL, idCli INT NOT NULL, INDEX IDX_COMMANDE_CLIENT (idCli), PRIMARY KEY(idCmd), CONSTRAINT FK_COMMANDE_CLIENT FOREIGN KEY (idCli) REFERENCES Client (idCli)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ligneCommande (idCmd INT NOT NULL, idProd INT NOT NULL, qteCmd INT NOT NULL, INDEX IDX_LIGNE_PRODUIT (idProd), PRIMARY KEY(idCmd, idProd), CONSTRAINT FK_LIGNE_COMMANDE FOREIGN KEY (idCmd) REFERENCES Commande (idCmd) ON DELETE CASCADE, CONSTRAINT FK_LIGNE_PRODUIT FOREIGN KEY (idProd) REFERENCES Produit (idProd)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ligneCommande');
        $this->addSql('DROP TABLE Commande');
        $this->addSql('DROP TABLE Produit');
        $this->addSql('DROP TABLE Client');
        $this->addSql('DROP TABLE Region');
        $this->addSql('DROP TABLE Departement');
    }
}