<?php

use Engine\Database\Migration;
use Engine\Database\Schema\Blueprint;
use Engine\Database\Schema\Schema;

class Migration_2025_12_30_104952_CreateUsersTable extends Migration
{
    public function up(): void
    {
        (new Schema($this->pdo))->createIfNotExists('users', function(Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        (new Schema($this->pdo))->dropIfExists('users');
    }
}
