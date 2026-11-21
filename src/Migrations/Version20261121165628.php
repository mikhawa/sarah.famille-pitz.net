<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Date du dernier e-mail « nouvel album » envoyé à la famille (bouton « Prévenir la famille » de l'administration)
 */
final class Version20261121165628 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Album : date du dernier e-mail « nouvel album » (notified_at)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE album ADD notified_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE album DROP notified_at');
    }
}
