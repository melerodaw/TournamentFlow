<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add RAWG metadata fields to game.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD rawg_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP COLUMN description');
        $this->addSql('ALTER TABLE game DROP COLUMN rawg_id');
    }
}