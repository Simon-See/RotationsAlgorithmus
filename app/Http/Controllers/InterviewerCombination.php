<?php

namespace App\Http\Controllers;

use Illuminate\Support\Collection;

class InterviewerCombination
{
    var float $prioirityNumber;
    var Pair $erst;
    var Pair $zweit;
    var Pair $protokoll;
    var Collection $interviewerArr;


    public function __construct(Pair $fst, Pair $snd, Pair $protocol)
    {
        $this->erst = $fst;
        $this->zweit = $snd;
        $this->protokoll = $protocol;
        $this->prioirityNumber = $fst->prioirityNumber + $snd->prioirityNumber + $protocol->prioirityNumber;
        $this->interviewerArr = collect([$this->erst, $this->zweit, $this->protokoll]);
    }

    public function __toString(): string
    {
        return "{" . $this->erst . " " . $this->zweit . " " . $this->protokoll . "}";
    }


    public function hatItler(): bool
    {
        return $this->erst->interviewer->istITler || $this->zweit->interviewer->istITler;
    }

    public  function isSameStudiengang(int $studienIdInterviewee): bool
    {
         return $studienIdInterviewee == $this->erst->interviewer->studienrichtungID || $studienIdInterviewee == $this->zweit->interviewer->studienrichtungID || $studienIdInterviewee == $this->protokoll->interviewer->studienrichtungID;
    }
}


