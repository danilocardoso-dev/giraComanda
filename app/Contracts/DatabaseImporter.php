<?php

namespace App\Contracts;

interface DatabaseImporter
{
    public function import(string $sourcePath): void;
}
