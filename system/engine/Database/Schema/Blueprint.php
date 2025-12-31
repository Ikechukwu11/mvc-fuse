<?php

namespace Engine\Database\Schema;

use PDO;

class Blueprint
{
  private string $table;
  private string $driver;
  private array $columns = [];
  private array $constraints = [];
  private bool $ifNotExists = false;

  protected ?string $pendingForeignColumn = null;
  protected ?string $pendingReferenceColumn = null;

  public function __construct(string $table, PDO $pdo, bool $ifNotExists = false)
  {
    $this->table = $table;
    $this->driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $this->ifNotExists = $ifNotExists;
  }

  public function toSql(): string
  {
    $columnsSql = implode(', ', $this->columns);

    $body = $columnsSql;
    if (!empty($this->constraints)) {
      $constraintsSql = '';
      foreach ($this->constraints as $constraintBuilder) {
        // Fix: constraintBuilder is a string in the provided reference, not a callable
        $constraintsSql .= ', ' . $constraintBuilder;
      }
      $body .= $constraintsSql;
    }

    $ifNotExists = $this->ifNotExists ? ' IF NOT EXISTS' : '';
    $engine = ($this->driver === 'mysql') ? ' ENGINE=InnoDB' : '';

    return "CREATE TABLE{$ifNotExists} {$this->table} ({$body}){$engine};";
  }

  public function increments(string $column = 'id'): self
  {
    if ($this->driver === 'sqlite') {
      $this->columns[] = "{$column} INTEGER PRIMARY KEY AUTOINCREMENT";
    } elseif ($this->driver === 'pgsql') {
      $this->columns[] = "{$column} SERIAL PRIMARY KEY";
    } else {
      $this->columns[] = "{$column} INT AUTO_INCREMENT PRIMARY KEY";
    }
    return $this;
  }

  public function id(string $column = 'id'): self
  {
    return $this->increments($column);
  }

  public function string(string $column, int $length = 255): self
  {
    if ($this->driver === 'sqlite') {
      $this->columns[] = "{$column} TEXT";
    } else {
      $this->columns[] = "{$column} VARCHAR({$length})";
    }
    return $this;
  }

  public function integer(string $column): self
  {
    if ($this->driver === 'sqlite') {
      $this->columns[] = "{$column} INTEGER";
    } else {
      $this->columns[] = "{$column} INT";
    }
    return $this;
  }

  public function text(string $column): self
  {
    $this->columns[] = "{$column} TEXT";
    return $this;
  }

  public function timestamps(string $column = 'created_at'): self
  {
    if ($column === 'created_at') {
      if ($this->driver === 'sqlite') {
        $this->columns[] = "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        $this->columns[] = "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
      } elseif ($this->driver === 'pgsql') {
        $this->columns[] = "created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT NOW()";
        $this->columns[] = "updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT NOW()";
      } else {
        $this->columns[] = "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        $this->columns[] = "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
      }
    } else {
      if ($this->driver === 'sqlite') {
        $this->columns[] = "{$column} TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
      } elseif ($this->driver === 'pgsql') {
        $this->columns[] = "{$column} TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT NOW()";
      } else {
        $this->columns[] = "{$column} TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
      }
    }
    return $this;
  }

  public function notNullable(): self
  {
    $lastColumn = array_pop($this->columns);
    $this->columns[] = $lastColumn . ' NOT NULL';
    return $this;
  }

  public function unique(): self
  {
    $lastColumn = array_pop($this->columns);
    $this->columns[] = $lastColumn . ' UNIQUE';
    return $this;
  }
}
