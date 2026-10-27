<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027183539 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création des tables media et media_tag, couverture des albums';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE media (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(10) NOT NULL, title VARCHAR(150) DEFAULT NULL, description LONGTEXT DEFAULT NULL, storage_path VARCHAR(255) NOT NULL, original_filename VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, size INT NOT NULL, width INT DEFAULT NULL, height INT DEFAULT NULL, duration INT DEFAULT NULL, taken_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, album_id INT DEFAULT NULL, uploaded_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_6A2CA10C279401A (storage_path), INDEX idx_media_taken_at (taken_at), INDEX idx_media_created_at (created_at), INDEX IDX_6A2CA10C1137ABCF (album_id), INDEX IDX_6A2CA10CA2B28FE8 (uploaded_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE media_tag (media_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_48D8C57EEA9FDD75 (media_id), INDEX IDX_48D8C57EBAD26311 (tag_id), PRIMARY KEY (media_id, tag_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE media ADD CONSTRAINT FK_6A2CA10C1137ABCF FOREIGN KEY (album_id) REFERENCES album (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE media ADD CONSTRAINT FK_6A2CA10CA2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE media_tag ADD CONSTRAINT FK_48D8C57EEA9FDD75 FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE media_tag ADD CONSTRAINT FK_48D8C57EBAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE album ADD cover_media_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE album ADD CONSTRAINT FK_39986E43329A1B2E FOREIGN KEY (cover_media_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_39986E43329A1B2E ON album (cover_media_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE media DROP FOREIGN KEY FK_6A2CA10C1137ABCF');
        $this->addSql('ALTER TABLE media DROP FOREIGN KEY FK_6A2CA10CA2B28FE8');
        $this->addSql('ALTER TABLE media_tag DROP FOREIGN KEY FK_48D8C57EEA9FDD75');
        $this->addSql('ALTER TABLE media_tag DROP FOREIGN KEY FK_48D8C57EBAD26311');
        $this->addSql('DROP TABLE media');
        $this->addSql('DROP TABLE media_tag');
        $this->addSql('ALTER TABLE album DROP FOREIGN KEY FK_39986E43329A1B2E');
        $this->addSql('DROP INDEX IDX_39986E43329A1B2E ON album');
        $this->addSql('ALTER TABLE album DROP cover_media_id');
    }
}
