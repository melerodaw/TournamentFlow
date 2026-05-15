<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260515120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Round entity and extend tournament_match with round/slot/participant1/participant2';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS "round" (id SERIAL NOT NULL, tournament_id INT NOT NULL, number INT NOT NULL, name VARCHAR(80) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_ROUND_TOURNAMENT ON "round" (tournament_id)');
        $this->addSql("DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'FK_ROUND_TOURNAMENT') THEN ALTER TABLE \"round\" ADD CONSTRAINT FK_ROUND_TOURNAMENT FOREIGN KEY (tournament_id) REFERENCES tournament (id) ON DELETE CASCADE; END IF; END $$;");

        $this->addSql('ALTER TABLE tournament_match ADD COLUMN IF NOT EXISTS round_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tournament_match ADD COLUMN IF NOT EXISTS slot INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE tournament_match ADD COLUMN IF NOT EXISTS participant1_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tournament_match ADD COLUMN IF NOT EXISTS participant2_id INT DEFAULT NULL');

        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_MATCH_ROUND ON tournament_match (round_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_MATCH_PART1 ON tournament_match (participant1_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_MATCH_PART2 ON tournament_match (participant2_id)');

        $this->addSql("DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'FK_MATCH_ROUND') THEN ALTER TABLE tournament_match ADD CONSTRAINT FK_MATCH_ROUND FOREIGN KEY (round_id) REFERENCES \"round\" (id) ON DELETE CASCADE; END IF; END $$;");
        $this->addSql("DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'FK_MATCH_PART1') THEN ALTER TABLE tournament_match ADD CONSTRAINT FK_MATCH_PART1 FOREIGN KEY (participant1_id) REFERENCES participant (id) ON DELETE SET NULL; END IF; END $$;");
        $this->addSql("DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'FK_MATCH_PART2') THEN ALTER TABLE tournament_match ADD CONSTRAINT FK_MATCH_PART2 FOREIGN KEY (participant2_id) REFERENCES participant (id) ON DELETE SET NULL; END IF; END $$;");
        $this->addSql('ALTER TABLE tournament ADD COLUMN IF NOT EXISTS champion_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_TOURNAMENT_CHAMPION ON tournament (champion_id)');
        $this->addSql("DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'FK_TOURNAMENT_CHAMPION') THEN ALTER TABLE tournament ADD CONSTRAINT FK_TOURNAMENT_CHAMPION FOREIGN KEY (champion_id) REFERENCES participant (id) ON DELETE SET NULL; END IF; END $$;");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tournament_match DROP CONSTRAINT IF EXISTS FK_MATCH_PART2');
        $this->addSql('ALTER TABLE tournament_match DROP CONSTRAINT IF EXISTS FK_MATCH_PART1');
        $this->addSql('ALTER TABLE tournament_match DROP CONSTRAINT IF EXISTS FK_MATCH_ROUND');

        $this->addSql('DROP INDEX IF EXISTS IDX_MATCH_PART2');
        $this->addSql('DROP INDEX IF EXISTS IDX_MATCH_PART1');
        $this->addSql('DROP INDEX IF EXISTS IDX_MATCH_ROUND');

        $this->addSql('ALTER TABLE tournament_match DROP participant2_id');
        $this->addSql('ALTER TABLE tournament_match DROP participant1_id');
        $this->addSql('ALTER TABLE tournament_match DROP slot');
        $this->addSql('ALTER TABLE tournament_match DROP round_id');

        $this->addSql('ALTER TABLE "round" DROP CONSTRAINT IF EXISTS FK_ROUND_TOURNAMENT');
        $this->addSql('DROP INDEX IF EXISTS IDX_ROUND_TOURNAMENT');
        $this->addSql('DROP TABLE IF EXISTS "round"');
    }
}
