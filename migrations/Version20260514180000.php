<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add registration deadline for tournaments and remove legacy creator enrollments.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM participant WHERE status = 'creator'");
        $this->addSql('ALTER TABLE tournament ADD registration_deadline_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NOW() NOT NULL');
        $this->addSql('UPDATE tournament SET registration_deadline_at = start_at');
        $this->addSql('ALTER TABLE tournament ALTER registration_deadline_at DROP DEFAULT');
        $this->addSql('CREATE INDEX idx_tournament_registration_deadline ON tournament (registration_deadline_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_tournament_registration_deadline');
        $this->addSql('ALTER TABLE tournament DROP registration_deadline_at');
    }
}