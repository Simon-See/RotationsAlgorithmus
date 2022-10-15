<?php

namespace App\Http\Controllers;


use App\Http\Middleware\TrimStrings;
use Illuminate\Support\Collection;

class Interviewer
{
    var string $name;
    var int $anzErstInterviews;
    var int $anzZweitInterviews;
    var int $anzProtokollant;
    var bool $istErfahren = false;
    var bool $istSemiErfahren = false;
    var bool $istUnerfahren = false;
    var bool $istUrspruenglichErfahren = false;
    var bool $istUrspruenglichSemiErfahren = false;
    var bool $istUrspruenglichUnerfahren = false;
    var bool $weiblich;
    var bool $istITler;
    var int $studienrichtungID;
    /**ZweiDimensionale Arrays für jeden Tag nächster Index*/
    var Collection $kannBeiZeitslot;
    var Collection $bolZugewieseneZeitslots;
    var int $id;
    var int $anzInterviewsAnAktuellenTag = 0;
    var Collection $anzInterviewsMitAnderen;

    public function __construct(int $id_, string $name_, bool $istErfahren_, bool $istSemiErfahren_, bool $istUnerfahren_, bool $weiblich_, bool $istITler_, int $Studienrichtung_,  Collection $kannBeiZeitslot_,Collection $emptyArrayZugewieseneZeitslots)
    {
        $this->id = $id_;
        $this->name = $name_;
        $this->anzErstInterviews = 0;//$anzErstInterviews_;
        $this->anzZweitInterviews = 0;//$anzZweitInterviews_;
        $this->anzProtokollant = 0;//$anzProtokollant_;
        $this->istErfahren = $istErfahren_;
        $this->istUrspruenglichErfahren = $istErfahren_;
        $this->istSemiErfahren = $istSemiErfahren_;
        $this->istUrspruenglichSemiErfahren = $istSemiErfahren_;
        $this->istUnerfahren = $istUnerfahren_;
        $this->istUrspruenglichUnerfahren = $istUnerfahren_;
        $this->weiblich = $weiblich_;
        $this->istITler = $istITler_;
        $this->studienrichtungID = $Studienrichtung_;
        $this->kannBeiZeitslot = $kannBeiZeitslot_;

        $this->bolZugewieseneZeitslots = $emptyArrayZugewieseneZeitslots;
        for ($x = 0; $x < sizeof($emptyArrayZugewieseneZeitslots); $x++) {
            for ($i = 0; $i < sizeof($emptyArrayZugewieseneZeitslots[$x]); $i++) {
                $this->bolZugewieseneZeitslots[$x][$i] = false;
            }
        }




    }

    function createArrAnzInterviewsMitAnderen(int $anzInterviewerGesamt){
        $this->anzInterviewsMitAnderen = collect()->pad($anzInterviewerGesamt, 0);

    }

    function wieVieleZeitslotsKannPersonNoch(int $day, int $slotOfDay): int
    {
        $anz = 0;

        for ($i = $day; $i < 3; $i++) {

            $beginIndex = $day == $i ? $slotOfDay : 0;
            for ($j = $beginIndex; $j < count($this->kannBeiZeitslot[$i]); $j++) {

                if ($this->kannBeiZeitslot[$i][$j])
                    $anz++;
            }
        }

        return $anz;
    }

    function wieVielInterviewsBereitsGehabt(): int
    {
        return $this->anzErstInterviews + $this->anzZweitInterviews + $this->anzProtokollant;
    }


    function erfahrungsStufeUpdaten()
    {
        if ($this->istErfahren)
            return;
        else if ($this->istSemiErfahren) {
            if ($this->anzZweitInterviews >= 2) {
                $this->istErfahren = true;
                $this->istSemiErfahren = false;
            }
        } else if ($this->istUnerfahren) {
            if ($this->anzProtokollant >= 3) {
                $this->istSemiErfahren = true;
                $this->istUnerfahren = false;
            }
        }

    }

    /* returns -1 if we havent had an Interview yet, 0 if we are on first working period, 1 if we are currently on break, 2 if we are in our second working period, 3 if we are done with our second working period */

    /**
     * Return Values:
     *  0: before 1st Working Period
     *  1: 1st Working Period
     *  2: Break
     *  3: 2nd Working Period
     *  4: 2nd Break
     *  5: 3rd Working Period
     * ...
     */
    function derzeitigeWorkingOrBreakPeriod(int $day, $slotOfDay): int
    {

        $returnValue = 0;

        for ($i = 0; $i < $slotOfDay; $i++) {
            //we were not working and now we are ->returnvalue ++
            if (($returnValue % 2 == 0) && $this->bolZugewieseneZeitslots[$day][$i]) {
                $returnValue += 1;
            } //we are working and now there is a break;
            elseif (($returnValue % 2 == 1) && !$this->bolZugewieseneZeitslots[$day][$i]) {
                $returnValue += 1;
            }
        }
        return $returnValue;





    }

    function anzInterviewsOderPausenHintereinander(int $day, int $slotOfDay, bool $anzInterviewsBestimmen): int
    {

        $anz = 0;

        for ($i = $slotOfDay - 1; $i >= 0; $i--) {

            //nicht mehr consecutive zugewíesen -> aktuelle anzahl zurückgeben
            if ($this->bolZugewieseneZeitslots[$day][$i] != $anzInterviewsBestimmen)
                return $anz;

            $anz++;
        }

        return $anz;
    }



    public function __toString(): string
    {
        return $this->name;
    }
}
