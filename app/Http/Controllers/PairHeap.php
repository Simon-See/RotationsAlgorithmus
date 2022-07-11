<?php

namespace App\Http\Controllers;

use SplMaxHeap;

class PairHeap extends SplMaxHeap
{


    public function compare(mixed $value1, mixed $value2): int
    {
        return $this->pairCompare($value1, $value2);
    }

    public function pairCompare(Pair $value1, Pair $value2): int
    {
        if($value1->prioirityNumber == $value2->prioirityNumber)
            return 0;
        if($value1->prioirityNumber > $value2->prioirityNumber)
            return 1;
        return -1;
    }


    public function __toString(): string
    {
        $str = "";

        $temp = new PairHeap();

        for ($i = 0; $i < $this->count(); $i++) {
            $curElement = $this->extract();
            $str = $str . $i .": " .  $curElement . "<br>";
            $temp->insert($curElement);
        }
        $str = $str . "--<br>";
        while(!$temp->isEmpty())
            $this->insert($temp->extract());

        return $str;

    }

}
