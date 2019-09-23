<?php

namespace SimpleQueryBuilder\Tests;

use PHPUnit\Framework\TestCase;
use SimpleQueryBuilder\Query;

class QueryTest extends TestCase
{
    private $pdo;

    public function setUp(): void
    {
        $options = array(
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        );
        $articleTbl = "CREATE TABLE `articles` (
                    `id`   INTEGER  NOT NULL
                                  PRIMARY KEY AUTOINCREMENT,
                    `name` VARCHAR  NOT NULL,
                    `body` TEXT     NOT NULL
                )";
        $articles = "INSERT INTO `articles` (`id`, `name`, `body`)
                     VALUES (1, 'title1', 'body1'),
                            (2, 'title2', 'body2'),
                            (3, 'title3', 'body3')";
        $this->pdo = new \PDO("sqlite::memory:", '', '', $options);
        $this->pdo->exec($articleTbl);
        $this->pdo->exec($articles);
    }

    public function testSelect()
    {
        $expected1 = [
            ['name' => 'title1', 'body' => 'body1'],
            ['name' => 'title2', 'body' => 'body2'],
            ['name' => 'title3', 'body' => 'body3'],
        ];
        $articles1 = (new Query($this->pdo, 'articles'))->select(['name', 'body'])->all();
        $expectedSql1 = "SELECT `name`, `body` FROM `articles`";
        $articlesSql1 = (new Query($this->pdo, 'articles'))->select(['name', 'body'])->toSql();
        $this->assertEquals($expectedSql1, $articlesSql1);
        $this->assertEquals($expected1, $articles1);

        $expected2 = ['name' => 'title1', 'body' => 'body1'];
        $articles2 = (new Query($this->pdo, 'articles'))->select(['name', 'body'])->where('id', 1)->fetch();
        $expectedSql2 = "SELECT `name`, `body` FROM `articles` WHERE `id` = '1'";
        $articlesSql2 = (new Query($this->pdo, 'articles'))->select(['name', 'body'])->where('id', 1)->toSql();
        $this->assertEquals($expectedSql2, $articlesSql2);
        $this->assertEquals($expected2, $articles2);

        $expected3 = [['name' => 'title3', 'body' => 'body3']];
        $expectedSql3 = "SELECT `name`, `body` FROM `articles` ORDER BY `id` DESC LIMIT 0, 1";
        $articlesSql3 = (new Query($this->pdo, 'articles'))
                            ->select(['name', 'body'])
                            ->orderBy('id', 'DESC')
                            ->limit(1)
                            ->toSql();
        $articles3 = (new Query($this->pdo, 'articles'))
                            ->select(['name', 'body'])
                            ->orderBy('id', 'DESC')
                            ->limit(1)
                            ->all();
        $this->assertEquals($expectedSql3, $articlesSql3);
        $this->assertEquals($expected3, $articles3);

        $expected4 = [
            ['name' => 'title2', 'body' => 'body2'],
            ['name' => 'title3', 'body' => 'body3'],
        ];
        $articles4 = (new Query($this->pdo, 'articles'))->select(['name', 'body'])->whereNot('id', 1)->all();
        $expectedSql4 = "SELECT `name`, `body` FROM `articles` WHERE `id` != '1'";
        $articlesSql4 = (new Query($this->pdo, 'articles'))->select(['name', 'body'])->whereNot('id', 1)->toSql();
        $this->assertEquals($expectedSql4, $articlesSql4);
        $this->assertEquals($expected4, $articles4);

        $expectedSql5 = "SELECT `name`, `body` FROM `articles` WHERE `name` = 'title1' AND `body` != 'body2'";
        $articlesSql5 = (new Query($this->pdo, 'articles'))
                            ->select(['name', 'body'])
                            ->where('name', 'title1')
                            ->whereNot('body', 'body2')
                            ->toSql();
        $this->assertEquals($expectedSql5, $articlesSql5);
        $expected5 = [['name' => 'title1', 'body' => 'body1']];
        $articles5 = (new Query($this->pdo, 'articles'))
                            ->select(['name', 'body'])
                            ->where('name', 'title1')
                            ->whereNot('body', 'body2')
                            ->all();
        $this->assertEquals($expected5, $articles5);
    }
}
