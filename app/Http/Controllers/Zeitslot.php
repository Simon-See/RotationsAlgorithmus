<?php


namespace App\Http\Controllers;

use App\Http\Controllers\InterviewerCombination;
use SplMaxHeap;

class Zeitslot
{

    var InterviewerCombination $interviewerCombination;
    var Interviewee $interviewee;
    var int $raumNummer;
    var int $slotOfDay;
    var int $day;

    /*public function __construct(Interviewer $erstInterviewerIn_, Interviewer $zweitInterviewerIn_, Interviewer $Protokollant_, Interviewee $interviewee_, Date $startZeit_, Date $endZeit_, int $raumNummer_)
    {
        $this->erstInterviewerIn = $erstInterviewerIn_;
        $this->zweitInterviewerIn = $zweitInterviewerIn_;
        $this->Protokollant = $Protokollant_;
        $this->interviewee = $interviewee_;
        $this->startZeit = $startZeit_;
        $this->endZeit = $endZeit_;
        $this->raumNummer = $raumNummer_;
    }*/


    public function __construct(int $day_, int $slotOfDay_, $raumNummer_)
    {
        $this->day = $day_;
        $this->slotOfDay = $slotOfDay_;
        $this->raumNummer = $raumNummer_;
    }


    public function __toString(): string
    {


        try {

            $strGleichesGeschlecht = ($this->interviewerCombination->erst->interviewer->weiblich == $this->interviewerCombination->zweit->interviewer->weiblich &&  $this->interviewerCombination->erst->interviewer->weiblich == $this->interviewerCombination->protokoll->interviewer->weiblich) ? "x;" : ";";
            $keinInterviewerGleicherStudiengang = $this->interviewee->studienrichtungID != $this->interviewerCombination->erst->interviewer->studienrichtungID && $this->interviewee->studienrichtungID != $this->interviewerCombination->zweit->interviewer->studienrichtungID &&$this->interviewee->studienrichtungID != $this->interviewerCombination->protokoll->interviewer->studienrichtungID ?"no;" : ";";

                //($this->interviewee->studienrichtungID != $this->interviewerCombination->erst->interviewer->studienrichtungID && $this->interviewee->studienrichtungID != $this->interviewerCombination->zweit->interviewer->studienrichtungID) ? (($this->interviewee->studienrichtungID != $this->interviewerCombination->protokoll->interviewer->studienrichtungID ) ? "x;" : "nur Protokollant gleich;") :";";


            return ($this->interviewee . ";" . $this->interviewerCombination->erst . ";" . $this->interviewerCombination->zweit . ";" . $this->interviewerCombination->protokoll .$strGleichesGeschlecht.$keinInterviewerGleicherStudiengang);
        } catch (\Error) {
            return "<br>";
        }


        //return ("{ raumNum: " . $this->raumNummer ." Day: " . $this->day . " slot: " . $this->slotOfDay ." Interviewee: " . $this->interviewee . " Interviewers: " . $this->interviewerCombination );
        //return ($this->interviewee . ";" . $this->interviewerCombination->erst . ";" . $this->interviewerCombination->zweit . ";" . $this->interviewerCombination->protokoll . "<br>");
        // TODO: Implement __toString() method.
    }

}
