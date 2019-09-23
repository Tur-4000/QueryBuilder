<?php

namespace QueryBuilder\Tests;

use PHPUnit\Framework\TestCase;
use QueryBuilder\Query;

class QueryTest extends TestCase
{
    private $pdo;

    public function setUp(): void
    {
        $articleTbl = "CREATE TABLE `articles` (
                    `id`   INTEGER  NOT NULL
                                  PRIMARY KEY AUTOINCREMENT,
                    `name` VARCHAR  NOT NULL,
                    `body` TEXT     NOT NULL
                )";
        $articles = "INSERT INTO `articles` (`name`, `body` )
                     VALUES ('title1', 'body1'),
                            ('title2', 'body2'),
                            ('title3', 'body3')";
        $this->pdo = new \PDO("sqlite::memory:");
        $this->pdo->exec($articleTbl);
        $this->pdo->exec($articles);
    }

    public function testSelect()
    {

    }
}
