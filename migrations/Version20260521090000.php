<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add swiss_rounds column to tournament for Swiss format support.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tournament ADD swiss_rounds INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tournament DROP swiss_rounds');
    }
}
