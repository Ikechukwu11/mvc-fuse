<?php

namespace Engine\Database\Schema;

use PDO;

class Schema
{
  private PDO $pdo;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  public function create(string $table, callable $callback): void
  {
    $blueprint = new Blueprint($table, $this->pdo);
    $callback($blueprint);
    $this->pdo->exec($blueprint->toSql());
  }

  public function createIfNotExists(string $table, callable $callback): void
  {
    $blueprint = new Blueprint($table, $this->pdo, true);
    $callback($blueprint);
    $this->pdo->exec($blueprint->toSql());
  }

  public function dropIfExists(string $table): void
  {
    $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
  }
}
