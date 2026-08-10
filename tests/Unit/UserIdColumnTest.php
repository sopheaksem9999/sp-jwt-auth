<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Sopheak\JwtAuth\Support\UserIdColumn;
use Sopheak\JwtAuth\Tests\TestCase;

final class UserIdColumnTest extends TestCase
{
    public function test_defaults_to_unsigned_big_integer_when_id_type_is_integer(): void
    {
        config()->set('sp-jwt-auth.id_type', 'integer');

        $blueprint = new Blueprint($this->app['db']->connection(), 'table', static function (Blueprint $table): void {
            UserIdColumn::apply($table);
        });

        $column = $blueprint->getColumns()[0];

        $this->assertSame('user_id', $column->name);
        $this->assertSame('bigInteger', $column->type);
        $this->assertTrue($column->unsigned);
    }

    public function test_uses_uuid_when_id_type_is_uuid(): void
    {
        config()->set('sp-jwt-auth.id_type', 'uuid');

        $blueprint = new Blueprint($this->app['db']->connection(), 'table', static function (Blueprint $table): void {
            UserIdColumn::apply($table);
        });

        $column = $blueprint->getColumns()[0];

        $this->assertSame('user_id', $column->name);
        $this->assertSame('uuid', $column->type);
    }

    public function test_supports_nullable_columns(): void
    {
        config()->set('sp-jwt-auth.id_type', 'uuid');

        $blueprint = new Blueprint($this->app['db']->connection(), 'table', static function (Blueprint $table): void {
            UserIdColumn::apply($table, nullable: true);
        });

        $this->assertTrue($blueprint->getColumns()[0]->nullable);
    }

    public function test_falls_back_to_integer_for_unknown_id_type(): void
    {
        config()->set('sp-jwt-auth.id_type', 'string');

        $blueprint = new Blueprint($this->app['db']->connection(), 'table', static function (Blueprint $table): void {
            UserIdColumn::apply($table);
        });

        $this->assertSame('bigInteger', $blueprint->getColumns()[0]->type);
    }
}
