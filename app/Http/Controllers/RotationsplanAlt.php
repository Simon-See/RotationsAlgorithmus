<?php


namespace App\Http\Controllers;
//namespace testNamespace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Interviewee;
use Illuminate\Support\Collection;
use http\Exception\InvalidArgumentException;



class RotationsplanAlt extends Controller
{

    public function index(){
        return view('index');
    }




    #= collect([new Interviewer()]);
    var int $curZeitSlot = 0;


    public function __construct()
    {
        //array $arrayOfIntererviewees,
        $arrayOfIntererviewees = collect([new Interviewee("A", true, false, "BWL", collect([true, false, false]))]);
        $arrayOfIntererviewees[] = new Interviewee("B", false, true, "Informatik", collect([true, true, true]));
        $arrayOfIntererviewees[] = new Interviewee("C", false, false, "Musik", collect([false, true, false]));

        //print($arrayOfIntererviewees);
        // int $anzInterviewsErsterTag,
        $anzInterviewsErsterTag = 3;

        // int $anzInterviewsZweiterTag,
        $anzInterviewsZweiterTag = 0;

        // int $anzInterviewsDritterTag,
        $anzInterviewsDritterTag = 0;

        // array $interviewerInnen,
        $interviewerInnen = collect([new Interviewer("AA", true, false, false, false, true, "Informatik", collect([true, true, true]),collect([true, true, true]))]);
        $interviewerInnen[] = new Interviewer("BB", false, false, true, true, false, "BWL",collect([true, true, true]), collect([true, false, false]));
        $interviewerInnen[] = new Interviewer("CC", false, false, true, false, false, "BWL", collect([true, true, true]),collect([true, false, true]));
        $interviewerInnen[] = new Interviewer("DD", true, false, false, true, false, "Musik", collect([true, true, true]),collect([false, true, false]));
        $interviewerInnen[] = new Interviewer("EE", true, false, false, false, true, "Informatik", collect([true, true, true]),collect([true, false, true]));
        $interviewerInnen[] = new Interviewer("FF", true, false, false, true, true, "Informatik", collect([true, true, true]),collect([true, true, true]));
        $interviewerInnen[] = new Interviewer("GG", true, false, false, false, false, "Elektrotechnik", collect([true, true, true]),collect([true, false, true]));

        print($interviewerInnen);
        print("<br>größe Interviewerinnen Array:");
        print(count($interviewerInnen)  . "<br>");
        // array $zeitSlots,
        $zeitSlots = collect([new Zeitslot(0), new Zeitslot(1), new Zeitslot(2)]); //collect([collect([new Zeitslot(0)]),collect([new Zeitslot(0)]),collect([new Zeitslot(0)])]);//collect([new Zeitslot(0), new Zeitslot(1), new Zeitslot(2)]);

        print("<br>Zeitslots:");
        print($zeitSlots);


        print("<br>");
        print("\n\n\nHELLAU \n\n");
        print("<br>");
        // int $anzRaeume
        $anzRaeume = 1;

        $this->mappingIntervieweesToTheInterviewers($arrayOfIntererviewees, $anzInterviewsErsterTag, $anzInterviewsZweiterTag, $anzInterviewsDritterTag, $interviewerInnen, $zeitSlots, $anzRaeume);
        //print($zeitSlots);
    }


    /** Zeitslots ist ein 2 Dimensionales Array das jeweils x Räume für den gleichen Zeitslot abdeckt
     * @throws \Exception
     */
    function mappingIntervieweesToTheInterviewers(Collection $arrayOfIntererviewees, int $anzInterviewsErsterTag, int $anzInterviewsZweiterTag, int $anzInterviewsDritterTag, Collection $interviewerInnen, Collection $zeitSlots, int $anzRaeume)
    {


        print($interviewerInnen);
        print("<br>größe Interviewerinnen Array:");
        print(count($interviewerInnen)  . "<br>");

        $anzInterviewsAvg = count($arrayOfIntererviewees) / count($interviewerInnen);
        $this->curZeitSlot = 0;

        for ($i = 0; $i < count($zeitSlots); $i++) {

            //resette bei allen Interviewern die anzahl interviews am aktuellen Tag wenn wir einen neuen Tag haben
            if ($i == $anzInterviewsErsterTag || $i == $anzInterviewsZweiterTag || $i == $anzInterviewsDritterTag) {
                for ($j = 0; $j < count($interviewerInnen); $j++) {


                    /** @var Interviewer $curInterviewerIn */
                    $curInterviewerIn = $interviewerInnen[$j];
                    $curInterviewerIn->anzInterviewsAnAktuellenTag = 0;

                    for ($l = 0; $l < count($curInterviewerIn->bolZugewieseneZeitslots); $l++) {
                        $curInterviewerIn->bolZugewieseneZeitslots[$l] = false;
                    }
                }
            }
            //usort($arrayOfIntererviewees, "cmp2Interviewees");


            $zeitSlotHeap = new ZeitslotHeap();

            //für den Zeitslot die bestmöglichen InterviewerInnen für alle Interviewees neu Berechnen
            for ($j = 0; $j < count($arrayOfIntererviewees); $j++) {
                /** @var Interviewee $curInterviewee */
                $curInterviewee = $arrayOfIntererviewees[$j];
                //wenn die Person zu dem Zeitslot kann und noch keinen Zeitslot zugewiesen bekommen hat -> beste Combination berechnen:
                if ($curInterviewee->kannBeiZeitslot && !$curInterviewee->zeitSlotBereitsZugewiesen) {
                    $zS = $this->perfectIntervieweeInterviewerCombination($interviewerInnen, $anzInterviewsAvg, $curInterviewee, $zeitSlots[$i]);
                    //wenn wir keinen gleichen Studiengang finden und der IT-Trait von den Interviewee ist gesetzt -> neuen Interviewee suchen
                    if ($curInterviewee->istITler && !$zS->interviewerCombination->erst->interviewerIn->istITler && !$zS->interviewerCombination->zweit->interviewerIn->istITler) {
                        continue;
                    }
                    $zeitSlotHeap->insert($zS);
                }
            }
            //die Räume für diesen Zeitslot voll machen
            $j = 0;
            while ($j < $anzRaeume && !$zeitSlotHeap->isEmpty()) {
                /** @var Zeitslot $bestCombination */
                $bestCombination = $zeitSlotHeap->extract();

                //check if one of the interviewees is already busy in another room -> recalculate the value
                if ($bestCombination->interviewerCombination->erst->interviewerIn->bolZugewieseneZeitslots[$this->curZeitSlot] || $bestCombination->interviewerCombination->zweit->interviewerIn->bolZugewieseneZeitslots[$this->curZeitSlot] || $bestCombination->interviewerCombination->protokoll->interviewerIn->bolZugewieseneZeitslots[$this->curZeitSlot]) {
                    //insert it into the heap -> new priority
                    $zeitSlotHeap->insert($this->perfectIntervieweeInterviewerCombination($interviewerInnen, $anzInterviewsAvg, $bestCombination->interviewee,$zeitSlots[$i]));
                    //try next best Combination in the next loop
                } else {
                    //bestCombination is indeed possible -> festsetzen
                    $zeitSlots[$this->curZeitSlot][$j] = $zS;
                    //raumzähler erhöhen
                    $j += 1;
                    // Dinge updaten (kann bei Zeitslot, anz Interviews, erfahren etc)
                    $zS->interviewee->zeitSlotBereitsZugewiesen = true;
                    $zS->interviewerCombination->erst->interviewerIn->bolZugewieseneZeitslots[$this->curZeitSlot] = true;
                    $zS->interviewerCombination->zweit->interviewerIn->bolZugewieseneZeitslots[$this->curZeitSlot] = true;
                    $zS->interviewerCombination->protokoll->interviewerIn->bolZugewieseneZeitslots[$this->curZeitSlot] = true;

                    $zS->interviewerCombination->erst->interviewerIn->anzInterviewsAnAktuellenTag++;
                    $zS->interviewerCombination->zweit->interviewerIn->anzInterviewsAnAktuellenTag++;
                    $zS->interviewerCombination->protokoll->interviewerIn->anzInterviewsAnAktuellenTag++;

                    $zS->interviewerCombination->erst->interviewerIn->anzErstInterviews++;
                    $zS->interviewerCombination->zweit->interviewerIn->anzZweitInterviews++;
                    $zS->interviewerCombination->protokoll->interviewerIn->anzProtokollant++;

                    $zS->interviewerCombination->erst->interviewerIn->erfahrungsStufeUpdaten();
                    $zS->interviewerCombination->zweit->interviewerIn->erfahrungsStufeUpdaten();
                    $zS->interviewerCombination->protokoll->interviewerIn->erfahrungsStufeUpdaten();
                }
            }

            $this->curZeitSlot++;
        }


        //TODO was wenn wir eine(n) haben der/die nicht zugewiesen wurde?


        /*usort($arrayOfIntererviewees, "cmp2Interviewees");
        // @var Interviewee $curInterviewee
        $curInterviewee = $arrayOfIntererviewees[0];
        if (!$curInterviewee->zeitSlotBereitsZugewiesen)
            throw  new InvalidArgumentException();
        */

    }

    function perfectIntervieweeInterviewerCombination(Collection $interviewerInnen, int $anzInterviewsAvg, Interviewee $curInterviewee, Zeitslot $curZeitslot): Zeitslot
    {
        //get all The possible Interviewers:
        $erst_W_Same = new PairHeap();
        $erst_W_Not = new PairHeap();
        $zweit_W_Same = new PairHeap();
        $zweit_W_Not = new PairHeap();
        $protokoll_W = new PairHeap();

        $erst_M_Same = new PairHeap();
        $erst_M_Not = new PairHeap();
        $zweit_M_Same = new PairHeap();
        $zweit_M_Not = new PairHeap();
        $protokoll_M = new PairHeap();

        //InterviewerInnen für Interviewee bestimmen:
        for ($i = 0; $i < count($interviewerInnen); $i++) {
            $this->insertingCurInterviewerIntoTheHeaps($interviewerInnen[$i], $this->curZeitSlot, $anzInterviewsAvg, $curInterviewee, $erst_W_Same, $erst_W_Not, $zweit_W_Same, $zweit_W_Not, $protokoll_W, $erst_M_Same, $erst_M_Not, $zweit_M_Same, $zweit_M_Not, $protokoll_M);
        }




        //InterviewerInnen und Interviee dem Zeitslot zuordnen
        $zS = new Zeitslot($this->curZeitSlot);
        $zS->interviewee = $curInterviewee;
        //beste InterviewerCombination:
        $zS->interviewerCombination =  $this->chooseTheCombinationOfInterviewers($erst_W_Same, $erst_W_Not, $zweit_W_Same, $zweit_W_Not, $protokoll_W, $erst_M_Same, $erst_M_Not, $zweit_M_Same, $zweit_M_Not, $protokoll_M);

        return $zS;
    }


    function chooseTheCombinationOfInterviewers(PairHeap $erst_W_Same, PairHeap $erst_W_Not, PairHeap $zweit_W_Same, PairHeap $zweit_W_Not, PairHeap $protokoll_W, PairHeap $erst_M_Same, PairHeap $erst_M_Not, PairHeap $zweit_M_Same, PairHeap $zweit_M_Not, PairHeap $protokoll_M): InterviewerCombination
    {
        $erstPossiblities = collect([]);
        $zweitPossiblities = collect([]);
        $protocolPossiblities = collect([]);

        //adding all the options for the first Interview spot -> if one heap ist empty just add null
        $erstPossiblities[0] = $erst_W_Same->isEmpty() ? null : $erst_W_Same->top();
        $erstPossiblities[1] = $erst_W_Not->isEmpty() ? null : $erst_W_Not->top();
        $erstPossiblities[2] = $erst_M_Same->isEmpty() ? null : $erst_M_Same->top();
        $erstPossiblities[3] = $erst_M_Not->isEmpty() ? null : $erst_M_Not->top();

        //adding all the options for the first Interview spot -> if one heap ist empty just add null
        $zweitPossiblities[0] = $zweit_W_Same->isEmpty() ? null : $zweit_W_Same->top();
        $zweitPossiblities[1] = $zweit_W_Not->isEmpty() ? null : $zweit_W_Not->top();
        $zweitPossiblities[2] = $zweit_M_Same->isEmpty() ? null : $zweit_M_Same->top();
        $zweitPossiblities[3] = $zweit_M_Not->isEmpty() ? null : $zweit_M_Not->top();

        //adding all the options for the Protocol spot -> if one heap ist empty just add null
        $protocolPossiblities[0] = $protokoll_W->isEmpty() ? null : $protokoll_W->top();
        $protocolPossiblities[1] = $protokoll_M->isEmpty() ? null : $protokoll_M->top();

        $femaleAndSame = new InterviewerCombinationHeap();
        $femaleXorSame = new InterviewerCombinationHeap();
        $maleAndDifferent = new InterviewerCombinationHeap();

        //alle erstPossibilities durchgehen
        for ($erst = 0; $erst < 4 && $erst < count($erstPossiblities); $erst++) {

            if ($erstPossiblities[$erst] == null)
                continue;
            $isThereFemale = $erst <= 1;
            $isThereSame = $erst % 2 == 0;

            for ($zweit = 0; $zweit < 4 && $zweit < count($zweitPossiblities); $zweit++) {
                if ($zweitPossiblities[$zweit] == null)
                    continue;
                $isThereFemale = $isThereFemale || $zweit <= 1;
                $isThereSame = $isThereSame || $zweit % 2 == 0;
                for ($dritt = 0; $dritt < 4 && $dritt < count($protocolPossiblities); $dritt++) {

                    if ($protocolPossiblities[$dritt] == null)
                        continue;
                    $isThereFemale = $isThereFemale || $dritt < 1;


                    if ($isThereSame && $isThereFemale)
                        $femaleAndSame->insert(new InterviewerCombination($erstPossiblities[$erst], $zweitPossiblities[$zweit], $protocolPossiblities[$dritt]));
                    else if (!$isThereSame && !$isThereFemale)
                        $maleAndDifferent->insert(new InterviewerCombination($erstPossiblities[$erst], $zweitPossiblities[$zweit], $protocolPossiblities[$dritt]));
                    else
                        $femaleXorSame->insert(new InterviewerCombination($erstPossiblities[$erst], $zweitPossiblities[$zweit], $protocolPossiblities[$dritt]));
                }

            }
        }
        //if possible return the best one
        if (!$femaleAndSame->isEmpty())
            return $femaleAndSame->top();
        else if (!$femaleXorSame->isEmpty())
            return $femaleXorSame->top();
        else
            return $maleAndDifferent->top();
    }


    function cmp2Interviewees(Interviewee $a, Interviewee $b): int
    {
        if ($b->zeitSlotBereitsZugewiesen)
            return -1;
        if ($a->zeitSlotBereitsZugewiesen)
            return 1;
        if (!$b->kannBeiZeitslot)
            return -1;
        if (!$a->kannBeiZeitslot)
            return 1;

        //return whoever has less timeSlots he or she can attend
        return $a->wieVieleZeitslotsKannPersonNoch($this->curZeitSlot) - $b->wieVieleZeitslotsKannPersonNoch($this->curZeitSlot);
    }


    /**
     * BisherigeValues:
     * [0] : PairHeap erstInterviewer, weiblich, Studiengang gleich
     * [1] : PairHeap erstInterviewer, weiblich, Studiengang NICHT gleich
     * [2] : PairHeap zweitInterviewer, weiblich, Studiengang gleich
     * [3] : PairHeap zweitInterviewer, weiblich, Studiengang NICHT gleich
     * [4] : PairHeap Protokollant, weiblich
     * [5] : PairHeap erstInterviewer, männlich, Studiengang gleich
     * [6] : PairHeap erstInterviewer, männlich, Studiengang NICHT gleich
     * [7] : PairHeap zweitInterviewer, männlich, Studiengang gleich
     * [8] : PairHeap zweitInterviewer, männlich, Studiengang NICHT gleich
     * [9] : PairHeap Protokollant, männlich
     */
    function insertingCurInterviewerIntoTheHeaps(Interviewer $cur, int $curZeitslot, int $anzInterviewsAvg, Interviewee $interviewee, PairHeap $erst_W_Same, PairHeap $erst_W_Not, PairHeap $zweit_W_Same, PairHeap $zweit_W_Not, PairHeap $protokoll_W, PairHeap $erst_M_Same, PairHeap $erst_M_Not, PairHeap $zweit_M_Same, PairHeap $zweit_M_Not, PairHeap $protokoll_M): void
    {


        //TODO EVTL BEI DEN GANZEN FAKTOREN NOCH EINE EXPONENTIALITÄT REIN BRINGEN -> mal verwenden?!?

        $curValue = 0;

        //kann die Person nicht zu dem Zeitpunkt -> einfach bisherigen returnen
        if (!$cur->kannBeiZeitslot[$curZeitslot] || $cur->bolZugewieseneZeitslots[$this->curZeitSlot])
            return;
        //wenn jemand 5 InterviewsHatte wird er gar nicht erst eingefügt oder 4 hintereinander  //TODO numbers
        if ($cur->anzInterviewsAnAktuellenTag >= 5 || $cur->anzInterviewsHintereinander($curZeitslot) >= 4)
            return;


        //Desto weniger Zeitslots die Person noch kann desto mehrwie viele Zeitslots kann die Person noch:
        $curValue -= $cur->wieVieleZeitslotsKannPersonNoch($curZeitslot) * 100;//TODO FAKTOR

        // man möchte in the long run möglichst nah ans Average ran -> wem noch einiges Fehlt bekommt bonus, wer drüber ist abzüge
        if ($cur->wieVielInterviewsBereitsGehabt() - $anzInterviewsAvg > 0) {
            //man hatte bereits zu viele
            $curValue -= ($cur->wieVielInterviewsBereitsGehabt() - $anzInterviewsAvg) * 100; //TODO Faktor
        } else {
            //man hatte zu wenige:
            $curValue += ($anzInterviewsAvg - $cur->wieVielInterviewsBereitsGehabt()) * 100; //TODO Faktor
        }


        //Die Interviewer bevorzugen die gerade schon gearbeitet haben
        /***0: before 1st Working Period
         *  1: 1st Working Period
         *  2: Break
         *  3: 2nd Working Period
         * ... */
        $workOrBreakPeriod = $cur->derzeitigeWorkingOrBreakPeriod($curZeitslot);

        //TODO gleichen Interviewer nicht nochmal zusammen?!



        //wenn die Zahl 4 oder höher -> wir haben schon 2 Working Periods hinter uns -> ungern noch eine
        if ($workOrBreakPeriod >= 4) {
            $curValue -= ($workOrBreakPeriod / 2 - 1) * 100; //TODO Faktor
        } else {


            //wenn er gerade eben schon Interview hatte:
            if ($workOrBreakPeriod % 2 == 1) {
                //anz der Interviews davor bestimmen
                $anzInterviewsDavor = $cur->anzInterviewsHintereinander($curZeitslot);

                if ($anzInterviewsDavor == 0)
                    throw new InvalidArgumentException();
                //wenn es 1 oder 2 Interview war -> super gerne/gerne noch eins
                if ($anzInterviewsDavor <= 2)
                    $curValue += ($anzInterviewsDavor * -1 + 3) * 100; //TODO Faktor
                //wenn es >3 Interviews waren -> ungern noch eins
                else
                    $curValue -= ($anzInterviewsDavor - 2) * 100; //TODO Faktor
            }
            //wenn er gerade Pause hatte sind wir indifferent //TODO maybe 1 Slot pause gut oder viele gut!?
        }


        //jobs ausgewogen machen -> punktZahlFürErstInterviews -= (anzErstInterviews - minInterviewType) * Faktor -> wir sorgen dafür das es ausgewogener wird
        $minAnzInterviewType = min($cur->anzErstInterviews, $cur->anzZweitInterviews, $cur->anzProtokollant);
        $faktorForAusgewogenheit = 100;//TODO Faktor


        //Ist die Person unqualifiziert -> Bonus auf das Protokollieren (muss dem des jobs ausgewogen machen entgegenwirken)
        if ($cur->istUnerfahren)
            $curValue += 100; //TODO Faktor

        if ($cur->weiblich)
            $protokoll_W->insert(new Pair($curValue - (($cur->anzProtokollant - $minAnzInterviewType) * $faktorForAusgewogenheit), $cur));
        else
            $protokoll_M->insert(new Pair($curValue - (($cur->anzProtokollant - $minAnzInterviewType) * $faktorForAusgewogenheit), $cur));

        //ist die Person nicht qualifiziert genug -> kann nicht in die anderen Heaps eingefügt werden
        if ($cur->istUnerfahren)
            return;


        //Ist die Person semiqualifiziert -> Bonus auf das Protokollieren (muss dem des jobs ausgewogen machen entgegenwirken)
        if ($cur->istSemiErfahren)
            $curValue += 100; //TODO Faktor


        //wenn Interviewer ITler ist, aber Interviewee nicht -> Punkte Abziehen, da dadurch die Itler länger im Pool bleiben
        if ($cur->istITler && !$interviewee->istITler)
            $curValue -= 100; //TODO Faktor


        //TODO gleicher Studiengang besser überprüfen?!?
        //gleicher Studiengang oder beide Itler:
        if ($cur->istITler && $interviewee->istITler || $cur->Studienrichtung == $interviewee->Studienrichtung) {
            $this->insertingErstAndZweitInterviwers($cur, $zweit_W_Same, $curValue, $minAnzInterviewType, $faktorForAusgewogenheit, $erst_W_Same, $zweit_M_Same, $erst_M_Same);
        } //nicht gleicher Studiengang
        else {
            $this->insertingErstAndZweitInterviwers($cur, $zweit_W_Not, $curValue, $minAnzInterviewType, $faktorForAusgewogenheit, $erst_W_Not, $zweit_M_Not, $erst_M_Not);
        }
    }


    public function insertingErstAndZweitInterviwers(Interviewer $cur, PairHeap $zweit_W_Not, float|int $curValue, mixed $minAnzInterviewType, int $faktorForAusgewogenheit, PairHeap $erst_W_Not, PairHeap $zweit_M_Not, PairHeap $erst_M_Not): void
    {
        if ($cur->weiblich) {
            $zweit_W_Not->insert(new Pair($curValue - (($cur->anzZweitInterviews - $minAnzInterviewType) * $faktorForAusgewogenheit), $cur));

            //ist die Person nicht qualifiziert genug -> kann nicht in die anderen Heaps eingefügt werden
            if (!$cur->istSemiErfahren) {
                $erst_W_Not->insert(new Pair($curValue - (($cur->anzErstInterviews - $minAnzInterviewType) * $faktorForAusgewogenheit), $cur));
            }

        } else {
            $zweit_M_Not->insert(new Pair($curValue - (($cur->anzZweitInterviews - $minAnzInterviewType) * $faktorForAusgewogenheit), $cur));
            //ist die Person nicht qualifiziert genug -> kann nicht in die anderen Heaps eingefügt werden
            if (!$cur->istSemiErfahren) {
                $erst_M_Not->insert(new Pair($curValue - (($cur->anzErstInterviews - $minAnzInterviewType) * $faktorForAusgewogenheit), $cur));
            }
        }
    }


}

