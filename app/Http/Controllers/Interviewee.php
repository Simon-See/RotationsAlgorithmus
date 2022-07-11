<?php
namespace App\Http\Controllers;

use Illuminate\Support\Collection;

class Interviewee
{
    var string $name;
    var bool $weiblich;
    var bool $istITler;
    var int $studienrichtungID;
    /**Zwei Dimensional*/
    var Collection $kannBeiZeitslot;
    var bool $zeitSlotBereitsZugewiesen = false;

    public function __construct(string $name_, bool $weiblich_, bool $istITler_, int $Studienrichtung_, Collection $kannBeiZeitslot_)
    {
        $this->name = $name_;
        $this->weiblich = $weiblich_;
        $this->istITler = $istITler_;
        $this->studienrichtungID = $Studienrichtung_;
        $this->kannBeiZeitslot = $kannBeiZeitslot_;
    }


    /*
    function wieVieleZeitslotsKannPersonNoch(int $curZeitslot): int
    {
        $anz = 0;
        for ($x = $curZeitslot; $x < count($this->kannBeiZeitslot); $x++) {
            if ($this->kannBeiZeitslot[$x])
                $anz++;
        }
        return $anz;
    }*/
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


    public function __toString(): string
    {
        return $this->name;
    }
}
