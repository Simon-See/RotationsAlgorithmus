<?php

namespace App\Http\Controllers;

use SplMaxHeap;

class InterviewerCombinationHeap extends SplMaxHeap
{


    public function compare(mixed $value1, mixed $value2): int
    {
        return $this->pairCompare($value1, $value2);
    }

    public function pairCompare(InterviewerCombination $value1, InterviewerCombination $value2): int
    {
        if ($value1->prioirityNumber == $value2->prioirityNumber)
            return 0;
        if ($value1->prioirityNumber > $value2->prioirityNumber)
            return 1;
        return -1;
    }


}
