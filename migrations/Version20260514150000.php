<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cascade rules for tournament subscriptions and match cleanup.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO participant (registered_at, seed, status, user_id, tournament_id) SELECT NOW(), NULL, 'creator', t.organizer_id, t.id FROM tournament t LEFT JOIN participant p ON p.tournament_id = t.id AND p.user_id = t.organizer_id WHERE p.id IS NULL");

        $this->addSql('ALTER TABLE match_participant DROP CONSTRAINT FK_E5061A392ABEACD6');
        $this->addSql('ALTER TABLE match_participant DROP CONSTRAINT FK_E5061A399D1C3019');
        $this->addSql('ALTER TABLE participant DROP CONSTRAINT FK_D79F6B11A76ED395');
        $this->addSql('ALTER TABLE participant DROP CONSTRAINT FK_D79F6B1133D1A3E7');
        $this->addSql('ALTER TABLE tournament_match DROP CONSTRAINT FK_BB0D551C33D1A3E7');
        $this->addSql('ALTER TABLE tournament_match DROP CONSTRAINT FK_BB0D551C5DFCD4B8');

        $this->addSql('ALTER TABLE match_participant ADD CONSTRAINT FK_E5061A392ABEACD6 FOREIGN KEY (match_id) REFERENCES tournament_match (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE match_participant ADD CONSTRAINT FK_E5061A399D1C3019 FOREIGN KEY (participant_id) REFERENCES participant (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE participant ADD CONSTRAINT FK_D79F6B11A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE participant ADD CONSTRAINT FK_D79F6B1133D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournament (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tournament_match ADD CONSTRAINT FK_BB0D551C33D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournament (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tournament_match ADD CONSTRAINT FK_BB0D551C5DFCD4B8 FOREIGN KEY (winner_id) REFERENCES participant (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE match_participant DROP CONSTRAINT FK_E5061A392ABEACD6');
        $this->addSql('ALTER TABLE match_participant DROP CONSTRAINT FK_E5061A399D1C3019');
        $this->addSql('ALTER TABLE participant DROP CONSTRAINT FK_D79F6B11A76ED395');
        $this->addSql('ALTER TABLE participant DROP CONSTRAINT FK_D79F6B1133D1A3E7');
        $this->addSql('ALTER TABLE tournament_match DROP CONSTRAINT FK_BB0D551C33D1A3E7');
        $this->addSql('ALTER TABLE tournament_match DROP CONSTRAINT FK_BB0D551C5DFCD4B8');

        $this->addSql('ALTER TABLE match_participant ADD CONSTRAINT FK_E5061A392ABEACD6 FOREIGN KEY (match_id) REFERENCES tournament_match (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE match_participant ADD CONSTRAINT FK_E5061A399D1C3019 FOREIGN KEY (participant_id) REFERENCES participant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE participant ADD CONSTRAINT FK_D79F6B11A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE participant ADD CONSTRAINT FK_D79F6B1133D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournament (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tournament_match ADD CONSTRAINT FK_BB0D551C33D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournament (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tournament_match ADD CONSTRAINT FK_BB0D551C5DFCD4B8 FOREIGN KEY (winner_id) REFERENCES participant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}