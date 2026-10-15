<?php

namespace App\Contracts;

interface DatabaseExporter
{
    public function export(string $destinationPath): void;
}
