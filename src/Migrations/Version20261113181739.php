<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rôles et visibilité : albums privés par défaut, médias « comme l'album » (privés sans album), verrou du
 * super-administrateur, droit d'envoi des membres.
 *
 * Choix de l'utilisateur : tout l'existant devient privé ; les administrateurs rendent publics les albums voulus.
 */
final class Version20261113181739 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Visibilité des albums et médias (tout privé), verrou super-admin, droit d\'envoi des membres';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE album ADD visibility VARCHAR(20) DEFAULT \'private\' NOT NULL, ADD visibility_locked TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE media ADD visibility VARCHAR(20) DEFAULT NULL, ADD visibility_locked TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE `user` ADD can_upload TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE album DROP visibility, DROP visibility_locked');
        $this->addSql('ALTER TABLE media DROP visibility, DROP visibility_locked');
        $this->addSql('ALTER TABLE `user` DROP can_upload');
    }
}
