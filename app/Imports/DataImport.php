<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

class DataImport implements ToArray
{
    public function array(array $array): void
    {
        // Do nothing, Excel::toArray returns the array automatically.
    }
}
