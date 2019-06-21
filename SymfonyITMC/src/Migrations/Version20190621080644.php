<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190621080644 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE book (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(300) NOT NULL, author VARCHAR(120) NOT NULL, illustrator VARCHAR(120) NOT NULL, editor VARCHAR(200) NOT NULL, isbn VARCHAR(13) NOT NULL, synopsis VARCHAR(3000) DEFAULT NULL, img_url VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE book_user (book_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_940E9D4116A2B381 (book_id), INDEX IDX_940E9D41A76ED395 (user_id), PRIMARY KEY(book_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE kind (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE kind_book (kind_id INT NOT NULL, book_id INT NOT NULL, INDEX IDX_EF95EE8430602CA9 (kind_id), INDEX IDX_EF95EE8416A2B381 (book_id), PRIMARY KEY(kind_id, book_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, pseudo VARCHAR(120) NOT NULL, firstname VARCHAR(120) NOT NULL, lastname VARCHAR(120) NOT NULL, email VARCHAR(320) NOT NULL, password CHAR(60) NOT NULL, admin TINYINT(1) NOT NULL, register_date DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE book_user ADD CONSTRAINT FK_940E9D4116A2B381 FOREIGN KEY (book_id) REFERENCES book (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE book_user ADD CONSTRAINT FK_940E9D41A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE kind_book ADD CONSTRAINT FK_EF95EE8430602CA9 FOREIGN KEY (kind_id) REFERENCES kind (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE kind_book ADD CONSTRAINT FK_EF95EE8416A2B381 FOREIGN KEY (book_id) REFERENCES book (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE book_user DROP FOREIGN KEY FK_940E9D4116A2B381');
        $this->addSql('ALTER TABLE kind_book DROP FOREIGN KEY FK_EF95EE8416A2B381');
        $this->addSql('ALTER TABLE kind_book DROP FOREIGN KEY FK_EF95EE8430602CA9');
        $this->addSql('ALTER TABLE book_user DROP FOREIGN KEY FK_940E9D41A76ED395');
        $this->addSql('DROP TABLE book');
        $this->addSql('DROP TABLE book_user');
        $this->addSql('DROP TABLE kind');
        $this->addSql('DROP TABLE kind_book');
        $this->addSql('DROP TABLE user');
    }
}
