<?php

namespace App\Http\Controllers;

use SplMaxHeap;

class ZeitslotHeap extends SplMaxHeap
{


    public function compare(mixed $value1, mixed $value2): int
    {
        return $this->pairCompare($value1, $value2);
    }

    public function pairCompare(Zeitslot $value1, Zeitslot $value2): int
    {
        //wenn einer der beiden Interviewees nur noch bei 2 Zeitslots kann -> hohe Priorität eher bevorzugen
        if ($value1->interviewee->wieVieleZeitslotsKannPersonNoch($value1->day,$value1->slotOfDay) <= 2 && $value2->interviewee->wieVieleZeitslotsKannPersonNoch($value2->day,$value2->slotOfDay) > $value1->interviewee->wieVieleZeitslotsKannPersonNoch($value1->day,$value1->slotOfDay))
            return -1;
        if ($value2->interviewee->wieVieleZeitslotsKannPersonNoch($value2->day,$value2->slotOfDay) <= 2 && $value2->interviewee->wieVieleZeitslotsKannPersonNoch($value2->day,$value2->slotOfDay) < $value1->interviewee->wieVieleZeitslotsKannPersonNoch($value1->day,$value1->slotOfDay))
            return 1;

        //TODO check if the double is working
        $val1 = ((double)$value1->interviewerCombination->prioirityNumber) / $value1->interviewee->wieVieleZeitslotsKannPersonNoch($value1->day,$value1->slotOfDay);
        $val2 = ((double)$value2->interviewerCombination->prioirityNumber) / $value2->interviewee->wieVieleZeitslotsKannPersonNoch($value2->day,$value2->slotOfDay);
        return $val1 >= $val2 ? 1 : 0;
    }


}
