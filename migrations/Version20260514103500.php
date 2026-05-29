<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514103500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add role column to user (admin|user) with default user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE \"user\" ADD role VARCHAR(10) NOT NULL DEFAULT 'user'");
        $this->addSql('ALTER TABLE "user" ALTER role DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP role');
    }
}
