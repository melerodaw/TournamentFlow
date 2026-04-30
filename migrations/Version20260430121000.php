<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260430121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique constraints and indexes missing from the initial migration.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_participant_tournament_user ON participant (tournament_id, user_id)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_match_slot ON match_participant (match_id, slot)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_match_participant ON match_participant (match_id, participant_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_tournament_status ON tournament (status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_tournament_format ON tournament (format)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_match_status ON tournament_match (status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_match_round ON tournament_match (round_number)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_participant_tournament_user');
        $this->addSql('DROP INDEX IF EXISTS uniq_match_slot');
        $this->addSql('DROP INDEX IF EXISTS uniq_match_participant');
        $this->addSql('DROP INDEX IF EXISTS idx_tournament_status');
        $this->addSql('DROP INDEX IF EXISTS idx_tournament_format');
        $this->addSql('DROP INDEX IF EXISTS idx_match_status');
        $this->addSql('DROP INDEX IF EXISTS idx_match_round');
    }
}
