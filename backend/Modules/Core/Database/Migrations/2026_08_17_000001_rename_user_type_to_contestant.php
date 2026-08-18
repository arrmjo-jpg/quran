<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ADR-015 §1: users.type stores the authentication surface, and its two
 * values are 'contestant' and 'admin'. The contestant value was 'user',
 * which became misleading once admin accounts were first-class — every
 * row in this table is a user, so "user" said nothing.
 *
 * Data only: the column type and index are unchanged. Reversible, since
 * the mapping is one-to-one and no other value is permitted.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('type', 'user')->update(['type' => 'contestant']);
    }

    public function down(): void
    {
        DB::table('users')->where('type', 'contestant')->update(['type' => 'user']);
    }
};
