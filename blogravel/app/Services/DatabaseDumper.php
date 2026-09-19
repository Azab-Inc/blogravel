<?php

namespace App\Services;

interface DatabaseDumper
{
    public function dump(string $directory): string;
}
