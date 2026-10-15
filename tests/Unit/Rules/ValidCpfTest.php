<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidCpf;
use PHPUnit\Framework\TestCase;

class ValidCpfTest extends TestCase
{
    public function test_accepts_valid_cpf(): void
    {
        $failures = [];

        (new ValidCpf)->validate('cpf', '52998224725', function (string $message) use (&$failures): void {
            $failures[] = $message;
        });

        $this->assertSame([], $failures);
    }

    public function test_rejects_repeated_digits(): void
    {
        $failures = [];

        (new ValidCpf)->validate('cpf', '11111111111', function (string $message) use (&$failures): void {
            $failures[] = $message;
        });

        $this->assertSame(['O CPF informado é inválido.'], $failures);
    }

    public function test_rejects_invalid_check_digits(): void
    {
        $failures = [];

        (new ValidCpf)->validate('cpf', '52998224724', function (string $message) use (&$failures): void {
            $failures[] = $message;
        });

        $this->assertSame(['O CPF informado é inválido.'], $failures);
    }
}
