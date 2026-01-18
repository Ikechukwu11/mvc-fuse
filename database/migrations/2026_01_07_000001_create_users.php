<?php
use Engine\Database\Migration;
use Engine\Database\Schema\Blueprint;
use Engine\Database\Schema\Schema;

class Migration_2026_01_07_000001_create_users extends Migration
{
    public function up(): void
    {
        (new Schema($this->pdo))->createIfNotExists('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique()->notNullable();
            $table->string('password')->notNullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        (new Schema($this->pdo))->dropIfExists('users');
    }
}

