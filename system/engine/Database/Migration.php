<?php

namespace Engine\Database;

use PDO;

abstract class Migration
{
  protected PDO $pdo;
  protected string $driver;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
    $this->driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
  }

  abstract public function up(): void;
  abstract public function down(): void;

  protected function exec(string $sql): bool|int
  {
    return $this->pdo->exec($sql);
  }
}
