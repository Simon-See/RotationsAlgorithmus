<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Interviewer;

class Pair
{
    var float $prioirityNumber;
    var Interviewer  $interviewer;

    public function __construct(float $pNum,mixed $object )
    {
        $this->prioirityNumber = $pNum;
        $this->interviewer = $object;
    }


    public function __toString(): string
    {
        return  $this->interviewer->name. ": ". ((int) $this->prioirityNumber);
    }
}
