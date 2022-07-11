<?php

namespace App\Http\Controllers;

use Illuminate\Support\Collection;

class Rotationsplan extends Controller
{

    public function index()
    {
        return view('index');
    }

    public function __construct()
    {

        print("<br>collection: <br>");
        $collection = $this->testRotationsPlanErstellen();
        foreach ($collection as $arr) {
            foreach ($arr as $el) {
                foreach ($el as $item) {
                    /** @var Zeitslot $curItem */
                    $curItem = $item;
                    if (($curItem != null))
                        print("<br>el: " . $curItem);
                }
            }
        }


    }

    function testRotationsPlanErstellen()
    {

        $pathToMeta = "MetaDaten.csv";
        $pathToInterviewer = "Interviewer.csv";
        $pathToInterviewees = "Interviewees.csv";

        //importing the MetaData
        #print("MetaDaten");
        $metaDaten = null;
        $isItStartRow = true;
        if (($csv_file = fopen($pathToMeta, "r")) !== false) {
            while (($read_data = fgetcsv($csv_file, 1000, ";")) !== false) {
                if ($isItStartRow) {
                    $isItStartRow = false;
                    continue;
                }
                //0: AnzahlSlotsFreitag;1: AnzahlSlotsSamstag;2: AnzahlSlotsSonntag;3: AnzahlRaeume
                $metaDaten = $read_data;
            }
            fclose($csv_file);
        }
        print("MetaDaten".$metaDaten[0]. $metaDaten[1]. $metaDaten[2]. $metaDaten[3]);
        $interviewerInnen = $this->createInterviewerORInterviewees($pathToInterviewer, true, $metaDaten[0], $metaDaten[1], $metaDaten[2]);
        print("<br><br><br>");
        $arrayOfIntererviewees = $this->createInterviewerORInterviewees($pathToInterviewees, false, $metaDaten[0], $metaDaten[1], $metaDaten[2]);

        print("<br> interviewerInnen: " . $interviewerInnen);
        print("<br> interviewees: " . $arrayOfIntererviewees . "<br><br>");

        return $this->rotationsPlanErstellen($arrayOfIntererviewees, collect([$metaDaten[0], $metaDaten[1], $metaDaten[2]]), $interviewerInnen, $metaDaten[3]);

    }


    function createInterviewerORInterviewees(string $filename, bool $creatingInterviewerATM, $anzSlotsFr, $anzSlotsSa, $anzSlotsSo): Collection
    {
        $resultingCollection = new Collection();
        $counter = 1;
        $isItStartRow = true;
        if (($csv_file = fopen($filename, "r")) !== false) {
            while (($arr = fgetcsv($csv_file, 1000, ";")) !== false) {
                if ($isItStartRow) {
                    $isItStartRow = false;
                    continue;
                }
                print("<br>arr: " . implode($arr));
                //arrays mit Zeitslots die die Personen können befüllen
                $kannBeiSlot = collect([new Collection(), new Collection(), new Collection()]);
                $emptyArr = collect([new Collection(), new Collection(), new Collection()]);

                $verschiebung = ($creatingInterviewerATM ? 1 : 0);

                for ($i = 0; $i < $anzSlotsFr; $i++) {
                    $kannBeiSlot[0][] = $arr[4 + $verschiebung] == 1; //Verfügbarkeit Freitag
                    $emptyArr[0][] = false;
                }
                for ($i = 0; $i < $anzSlotsSa; $i++) {
                    $kannBeiSlot[1][] = $arr[5 + $verschiebung] == 1; //Verfügbarkeit Samstag
                    $emptyArr[1][] = false;
                }
                for ($i = 0; $i < $anzSlotsSo; $i++) {
                    $kannBeiSlot[2][] = $arr[6 + $verschiebung] == 1; //Verfügbarkeit Sonntag
                    $emptyArr[2][] = false;
                }

                if ($creatingInterviewerATM) {
                    $resultingCollection[] = new Interviewer($counter, $arr[0] . " " . $arr[1], $arr[3] == 2, $arr[3] == 1, $arr[3] == 0, $arr[4] == 1, $arr[2] == 1, $arr[8], $kannBeiSlot, $emptyArr);
                } else {
                    $resultingCollection[] = new Interviewee($arr[0] . " " . $arr[1], $arr[3] == 1, $arr[2] == 1, $arr[7], $kannBeiSlot);
                }
                $counter++;
            }
            fclose($csv_file);
        }
        return $resultingCollection;
    }


    /** returns Zeitslots 3 Dimensional Array */
    function rotationsPlanErstellen(Collection $arrayOfIntererviewees, Collection $anzInterviewsTagX, Collection $interviewer, int $anzRaeume): Collection
    {

        $anzInterviewsAvg = ((float)count($arrayOfIntererviewees)) / count($interviewer);

        /**Generating the ZeitslotArray*/

        //Zeitslots 1st Dimension: the x Days
        //Zeitslots 2nd Dimension: the Slots themselves
        //Zeitslots 3rd Dimension: the x Rooms

        $zeitSlotArray = collect([]);

        $anzSlotsGesamt = 0;
        for ($x = 0; $x < count($anzInterviewsTagX); $x++) {
            //1st Dimension
            $zeitSlotArray[] = collect([]);
            for ($j = 0; $j < $anzInterviewsTagX[$x]; $j++) {
                //2nd Dimension
                $zeitSlotArray[$x][] = collect([]);
                for ($i = 0; $i < $anzRaeume; $i++) {
                    //3rd Dimension
                    $zeitSlotArray[$x][$j][] = new Zeitslot($x, $j, $i);
                    $anzSlotsGesamt++;
                }
            }
        }
        print($zeitSlotArray);


        /** Über das ZeitslotArray iterieren und alle Zeitslots befüllen */

        //iterieren über die einzelnen Tage
        for ($x = 0; $x < count($anzInterviewsTagX); $x++) {
            print("<br><br><br>");
            print("x:" . $x);
            //resetten der anz Interviews am aktuellen Tag bei allen Interviewern
            for ($h = 0; $h < count($interviewer); $h++) {
                /** @var Interviewer $curInterviewer */
                $curInterviewer = $interviewer[$h];
                $curInterviewer->anzInterviewsAnAktuellenTag = 0;
            }
            $interviewerIntervieweeCombinationHeap = new ZeitslotHeap();

            /**iterieren über die einzelnen Zeiten*/
            for ($j = 0; $j < $anzInterviewsTagX[$x]; $j++) {
                print("<br><br>");
                print("__j:" . $j . "<br>");
                $interviewerIntervieweeCombinationHeap = new ZeitslotHeap();

                //bestmögliche InterviewerInnen für den Zeitslot berechnen -> für Zeitslot die bestmöglichen InterviewerInnen für alle Interviewees neu berechnen
                for ($k = 0; $k < count($arrayOfIntererviewees); $k++) {

                    /** @var Interviewee $curInterviewee */
                    $curInterviewee = $arrayOfIntererviewees[$k];
                    if($curInterviewee->zeitSlotBereitsZugewiesen){
                        continue;

                    }


                    print("<br>CurInterviewee: " . $curInterviewee);
                    //Die Person muss zu dem Zeitpunkt können und noch kein Interviewslot bekommen haben
                    if ($curInterviewee->kannBeiZeitslot[$x][$j] && !$curInterviewee->zeitSlotBereitsZugewiesen) {
                        print("<br>    KannBeiZeitslot");

                        $tempInterviewerCombination = $this->berechnenBesteInterviewerIntervieweeCombination($interviewer, $anzInterviewsAvg, $curInterviewee, $x, $j, $anzSlotsGesamt);
                        print("<br> TempInterviewerCombination: " . $tempInterviewerCombination);

                        //Es darf nicht null sein
                        if ($tempInterviewerCombination != null) {
                            print("<br>TempInterviewerCombination Is Not Null");
                            $bestInterviewerIntervieweeCombination = new Zeitslot(0, 0, -1);
                            $bestInterviewerIntervieweeCombination->interviewerCombination = $tempInterviewerCombination;
                            $bestInterviewerIntervieweeCombination->interviewee = $curInterviewee;
                            //und wenn wir keinen gleichen Studiengang finden und der IT-Trait von den Interviewee ist gesetzt -> diesen Interviewee überspringen
                            //if($curInterviewee->istITler ||( !$curInterviewee->istITler && (!$tempInterviewerCombination->erst->interviewer->istITler && !$tempInterviewerCombination->zweit->interviewer->istITler)))
                            $interviewerIntervieweeCombinationHeap->insert($bestInterviewerIntervieweeCombination);

                        }
                    }
                }

                print("<br>" . "Heapsize: " . count($interviewerIntervieweeCombinationHeap));

                /**iterieren über die Räume*/
                for ($i = 0; $i < $anzRaeume; $i++) {
                    print("<br>");
                    print("____i:" . $i);

                    $tempHeap = $interviewerIntervieweeCombinationHeap;
                    $interviewerIntervieweeCombinationHeap = new ZeitslotHeap();

                    while(!$tempHeap->isEmpty()){
                        /** @var Zeitslot $curElement */
                        $curElement = $tempHeap->extract();
                        if(!$curElement->interviewee->zeitSlotBereitsZugewiesen )
                            $interviewerIntervieweeCombinationHeap->insert($curElement);
                        else
                            print("fällt Raus: " . $curElement->interviewee->name);
                    }


                    //wenn wir keinen Interviewee mehr assignen können zu diesem Zeitpunkt -> nächster Zeitpunkt
                    if ($interviewerIntervieweeCombinationHeap->isEmpty()) {
                        print("No InterviewerIntervieweeCombination");
                        /** @var Zeitslot $curZeitslot */
                        $curZeitslot = $zeitSlotArray[$x][$j][$i];
                        $curZeitslot->interviewee = new Interviewee("-", false, false, -1, collect([]));
                        $tempPair = new Pair(-1, new Interviewer(-1, "-", false, false, false, false, false, -1, collect([]), collect([])));
                        $curZeitslot->interviewerCombination = new InterviewerCombination($tempPair, $tempPair, $tempPair);
                        continue;
                    }



                    //Räume für diesen Zeitslot vollmachen
                    /** @var Zeitslot $bestCombination */
                    $bestCombination = $interviewerIntervieweeCombinationHeap->extract();

                    print("BestCombination Davor: " . $bestCombination->interviewee);

                    //check if one of the interviewers is already busy in another room -> recalculate the value
                    while (($bestCombination->interviewerCombination->erst->interviewer->id != -1 && $bestCombination->interviewerCombination->erst->interviewer->bolZugewieseneZeitslots[$x][$j]) || ($bestCombination->interviewerCombination->zweit->interviewer->id != -1 && $bestCombination->interviewerCombination->zweit->interviewer->bolZugewieseneZeitslots[$x][$j]) || ($bestCombination->interviewerCombination->protokoll->interviewer->id != -1 && $bestCombination->interviewerCombination->protokoll->interviewer->bolZugewieseneZeitslots[$x][$j])) {
                        //TODO WE NEED TO DELETE THE INTERVIEWER OUT OF THE ARRAY WE GIVE TO THAT METHOD -> no we dont cause zugewieseneZeitslot
                        print("inerviewee: " . $bestCombination->interviewee);
                        $val = $this->berechnenBesteInterviewerIntervieweeCombination($interviewer, $anzInterviewsAvg, $bestCombination->interviewee, $x, $j, $anzSlotsGesamt);
                        print("inerviewee: " . $bestCombination->interviewee);
                        print("<br>val:" . $val);
                        if ($val != null) {

                            $tempZeitslot = new Zeitslot(0, 0, -1);
                            $tempZeitslot->interviewerCombination = $val;
                            $tempZeitslot->interviewee = $bestCombination->interviewee;


                            $interviewerIntervieweeCombinationHeap->insert($tempZeitslot);
                        }
                        if ($interviewerIntervieweeCombinationHeap->isEmpty()) {
                            $bestCombination = null;
                            break;
                        }


                        $bestCombination = $interviewerIntervieweeCombinationHeap->extract();
                    }


                        //wenn die BesteCombination aus Null Interviewern besteht -> continue
                    if ($bestCombination == null /*||$bestCombination->interviewerCombination->erst->interviewer->id == -1*/) {
                        $zeitSlotArray[$x][$j][$i]->interviewee = new Interviewee("-", false, false, -1, collect([]));
                        //$zeitSlotArray[$x][$j][$i] = new Pair(-1,new Interviewer(-1,"Test",false,false,false,false,false,-1,collect([]),collect([])));
                        $tempPair = new Pair(-1, new Interviewer(-1, "-", false, false, false, false, false, -1, collect([]), collect([])));
                        $zeitSlotArray[$x][$j][$i]->interviewerCombination = new InterviewerCombination($tempPair, $tempPair, $tempPair);
                        continue;
                    }
                    print("BestCombination Danach: " . $bestCombination->interviewee);
                    print("x: " . $x . " " . $j . " " . $i);

                    /** @var Zeitslot $curZeitslot */
                    $curZeitslot = $zeitSlotArray[$x][$j][$i];

                    //wir haben nun die Beste Kombination aus interviewern und Interviewee -> speichern im ZeitslotArray
                    $curZeitslot->interviewerCombination = $bestCombination->interviewerCombination;
                    $curZeitslot->interviewee = $bestCombination->interviewee;
                    print("bereitsZugewiesen davor: ". $curZeitslot->interviewee->name . " " . $curZeitslot->interviewee->zeitSlotBereitsZugewiesen . " false: " . false ." true: " . true );
                    // Dinge updaten (kann bei Zeitslot, anz Interviews, erfahren etc)
                    $curZeitslot->interviewee->zeitSlotBereitsZugewiesen = true;

                    for ($l = 0; $l < count($curZeitslot->interviewerCombination->interviewerArr); $l++) {
                        /** @var Pair $tempInterviewer */
                        $tempInterviewer = $curZeitslot->interviewerCombination->interviewerArr[$l];
                        $tempInterviewer->interviewer->bolZugewieseneZeitslots[$x][$j] = true;
                        $tempInterviewer->interviewer->anzInterviewsAnAktuellenTag++;
                        $tempInterviewer->interviewer->erfahrungsStufeUpdaten();
                    }

                    $curZeitslot->interviewerCombination->erst->interviewer->anzErstInterviews++;
                    $curZeitslot->interviewerCombination->zweit->interviewer->anzZweitInterviews++;
                    $curZeitslot->interviewerCombination->protokoll->interviewer->anzProtokollant++;

                    #print("<br>CurZeitSlot: " . $curZeitslot->interviewerCombination->erst->interviewer->name);
                }
            }
        }

        for ($day = 0; $day<count($zeitSlotArray); $day++) {
            print("Day ".$day."<br>");
            print("Slot;Raum;Interviewee;Erstinterviewer;Zweitinterviewer;Protokollant<br>");
            for($slot = 0; $slot < count($zeitSlotArray[$day]); $slot++) {
                for($room = 0; $room < count($zeitSlotArray[$day][$slot]); $room++) {
                    print($slot . ";" . $room . ";" . $zeitSlotArray[$day][$slot][$room]);
                }
            }
            print("<br><br><br>");
        }
        return $zeitSlotArray;
    }


    function berechnenBesteInterviewerIntervieweeCombination(Collection $interviewer, float $anzInterviewsAVG, Interviewee $curInterviewee, int $day, int $slotOfTheDay, int $anzSlotsGesamt): ?InterviewerCombination
    {


        /**
         * [0] : $erst_W_Same   PairHeap erstInterviewer, weiblich, Studiengang gleich
         * [1] : $erst_W_Not    PairHeap erstInterviewer, weiblich, Studiengang NICHT gleich
         * [2] : $erst_M_Same   PairHeap erstInterviewer, männlich, Studiengang gleich
         * [3] : $erst_M_Not    PairHeap erstInterviewer, männlich, Studiengang NICHT gleich
         * [4] : $zweit_W_Same  PairHeap zweitInterviewer, weiblich, Studiengang gleich
         * [5] : $zweit_W_Not   PairHeap zweitInterviewer, weiblich, Studiengang NICHT gleich
         * [6] : $zweit_M_Same  PairHeap zweitInterviewer, männlich, Studiengang gleich
         * [7] : $zweit_M_Not   PairHeap zweitInterviewer, männlich, Studiengang NICHT gleich
         * [8] : $protokoll_W   PairHeap Protokollant, weiblich
         * [9] : $protokoll_M   PairHeap Protokollant, männlich
         */
        $interviewerHeaps = collect([new PairHeap(), new PairHeap(), new PairHeap(), new PairHeap(), new PairHeap(), new PairHeap(), new PairHeap(), new PairHeap(), new PairHeap(), new PairHeap()]);

        if($curInterviewee->zeitSlotBereitsZugewiesen)
            return $interviewerHeaps;

        //InterviewerInnen für Interviewee bestimmen:
        for ($i = 0; $i < count($interviewer); $i++) {
            $this->insertingCurInterviewerIntoTheHeaps($interviewer[$i], $day, $slotOfTheDay, $anzInterviewsAVG, $curInterviewee, $interviewerHeaps, $anzSlotsGesamt);
            #print("<br> größe Heaps: " . count($interviewerHeaps[0]) . " " . count($interviewerHeaps[1]) . " " . count($interviewerHeaps[2]) . " " . count($interviewerHeaps[3]) . " " . count($interviewerHeaps[4]) . " " . count($interviewerHeaps[5]) . " " . count($interviewerHeaps[6]) . " " . count($interviewerHeaps[7]) . " " . count($interviewerHeaps[8]) . " " . count($interviewerHeaps[9]));
        }


        /**Richtige Kombination der möglichen Interviewer bestimmen:*/

        /*$posibillitiesArray = collect([]);
        print("<br>PosibilitiesArray: ");
        //nehme den bestmöglichen Interviewer aus der Priority Que falls ein solcher existiert
        for ($i = 0; $i < count($interviewerHeaps); $i++) {
            /** @var PairHeap $tempPairHeap *//*
            $tempPairHeap = $interviewerHeaps[$i];
            print(", " . ($tempPairHeap->isEmpty() ? "-" : $tempPairHeap->top()));
            $posibillitiesArray[] = $tempPairHeap->isEmpty() ? null : $tempPairHeap->top();
        }*/


        $femaleAndSame = new InterviewerCombinationHeap();
        $femaleXorSame = new InterviewerCombinationHeap();
        $maleAndDifferent = new InterviewerCombinationHeap();

        for ($i = 0; $i < 4; $i++) {

            //solange den jeweiligen InterviewerHeap durchgehen bis er leer ist
            /** @var PairHeap $tempPairHeapFST */
            $tempPairHeapFST = $interviewerHeaps[$i];
            while (!$tempPairHeapFST->isEmpty()) {


                /** @var Pair $fst */
                $fst = $tempPairHeapFST->extract();

                $isThereFemale = $i <= 1;
                $isThereSame = $i % 2 == 0;


                for ($j = 0; $j < 4; $j++) {

                    /** @var PairHeap $tempPairHeapSND */
                    $tempPairHeapSND = $interviewerHeaps[$j + 4];
                    while (!$tempPairHeapSND->isEmpty()) {

                        /** @var Pair $snd */
                        $snd = $tempPairHeapSND->extract();

                        //nicht null und der gleiche Interviewer darf es auch nicht sein
                        if ($snd == null || $fst->interviewer->id == $snd->interviewer->id)
                            continue;


                        $isThereFemale = $isThereFemale || $j <= 1;
                        $isThereSame = $isThereSame || $j % 2 == 0;

                        for ($k = 0; $k < 2; $k++) {


                            /** @var PairHeap $tempPairHeapTRD */
                            $tempPairHeapTRD = $interviewerHeaps[$k + 8];
                            while (!$tempPairHeapTRD->isEmpty()) {

                                /** @var Pair $trd */
                                $trd = $tempPairHeapTRD->extract();

                                #print("tempPairHeapTRD Size: "  . count($tempPairHeapTRD));

                                //nicht null und der gleiche Interviewer darf es auch nicht sein
                                if ($trd == null || $fst->interviewer->id == $trd->interviewer->id || $snd->interviewer->id == $trd->interviewer->id)
                                    continue;


                                $isThereFemale = $isThereFemale || $k == 0;

                                //insert The Combination in the Heap ist Belongs
                                $newInterviewerCombination = new InterviewerCombination($fst, $snd, $trd);
                                #print("<br>Interviewee:" . $curInterviewee . " IntervieweeCombination: " . $newInterviewerCombination);


                                if ($isThereSame && $isThereFemale)
                                    $femaleAndSame->insert($newInterviewerCombination);
                                else if (!$isThereSame && !$isThereFemale)
                                    $maleAndDifferent->insert($newInterviewerCombination);
                                else
                                    $femaleXorSame->insert($newInterviewerCombination);
                            }
                        }
                    }
                }
                /*$tempPair = new Pair(-1, new Interviewer(-1, "NULL", false, false, false, false, false, -1, collect([]), collect([])));
                $maleAndDifferent->insert(new InterviewerCombination($tempPair,$tempPair,$tempPair));*/
            }
        }

        //if possible return the best one
        if (!$femaleAndSame->isEmpty())
            return $femaleAndSame->top();
        else if (!$femaleXorSame->isEmpty())
            return $femaleXorSame->top();
        else if (!$maleAndDifferent->isEmpty())
            return $maleAndDifferent->top();

        #print("<br>Returning Null");
        return null;
    }


    function insertingCurInterviewerIntoTheHeaps(Interviewer $cur, int $day, int $slotOfTheDay, float $anzInterviewsAVG, Interviewee $interviewee, Collection $interviewerHeaps, int $anzSlotsGesamt): void
    {

        //desto höher desto Besser
        $prioNumCurInterviewer = 0;

        //Die Person kann zu dem Zeitpunkt nicht:
        if (!$cur->kannBeiZeitslot[$day][$slotOfTheDay] || $cur->bolZugewieseneZeitslots[$day][$slotOfTheDay])
            return;
        //wenn jemand 5 InterviewsHatte wird er gar nicht erst eingefügt oder 4 hintereinander
        if ($cur->anzInterviewsAnAktuellenTag >= 5 || $cur->anzInterviewsOderPausenHintereinander($day, $slotOfTheDay, true) >= 4)
            return;

        //Desto weniger Zeitslots die Person noch kann desto höher die Prio
        $prioNumCurInterviewer += ($anzSlotsGesamt - $cur->wieVieleZeitslotsKannPersonNoch($day, $slotOfTheDay)) * 10; //TODO FAKTOR

        //man möchte in the long run möglichst nah ans Average ran -> wem noch einiges Fehlt bekommt bonus, wer drüber ist Abzüge
        $prioNumCurInterviewer -= (((float)$cur->wieVielInterviewsBereitsGehabt()) - $anzInterviewsAVG) * 70; //TODO FAKTOR
        //print("<br> avg:".(-(($cur->wieVielInterviewsBereitsGehabt()) - $anzInterviewsAVG) * 100));

        //Die Interviewer bevorzugen die gerade schon gearbeitet haben
        /***0: before 1st Working Period
         *  1: 1st Working Period
         *  2: Break
         *  3: 2nd Working Period
         * ... */
        $workOrBreakPeriod = $cur->derzeitigeWorkingOrBreakPeriod($day, $slotOfTheDay);



        //schon 2 WorkingPeriods mit breakes hinter uns -> nicht so gerne nochmal
        //if ($workOrBreakPeriod >= 4) {
          //  $prioNumCurInterviewer -= $workOrBreakPeriod * 40; //TODO Faktor
        //} else {
            //wenn er gerade eben schon Interview hatte:
        /*
            if ($workOrBreakPeriod % 2 == 1) {
                //anz der Interviews davor bestimmen
                $anzInterviewsDavor = $cur->anzInterviewsOderPausenHintereinander($day, $slotOfTheDay, true);

                //wenn es 1 oder 2 Interview war -> super gerne/gerne noch eins
                if ($anzInterviewsDavor <= 2)
                    $prioNumCurInterviewer += ($anzInterviewsDavor * -1 + 3) * 40; //TODO Faktor
                //wenn es >3 Interviews waren -> ungern noch eins
                else
                    $prioNumCurInterviewer -= ($anzInterviewsDavor - 2) * 100; //TODO Faktor
            } //wenn er gerade Pause hatte -> 1 slot Pause gut
            else {
                //anz der Pausen davor bestimmen
                $anzPausenDavor = $cur->anzInterviewsOderPausenHintereinander($day, $slotOfTheDay, false);
                //1 Pause:
                if ($anzPausenDavor == 1) {
                    $prioNumCurInterviewer += 100; //TODO Faktor
                } //mehr als einen Slot am Stück Pause -> schlecht
                else {
                    $prioNumCurInterviewer -= 100; //TODO Faktor
                }
            }*/



            //wenn Interviewer ITler ist, aber Interviewee nicht -> Punkte Abziehen, da dadurch die Itler länger im Pool bleiben
            if ($cur->istITler && !$interviewee->istITler)
                $prioNumCurInterviewer -= 20; //TODO Faktor

            //wenn beide ITler -> Bonus
            //if ($cur->istITler && $interviewee->istITler)
            //    $prioNumCurInterviewer += 100; //TODO Faktor

            #print("<br>CurrentInterviewer: " . $cur->name . " prioNum: " . $prioNumCurInterviewer);

            //wenn die Person noch kein Interview hatte heute -> ungern eins
            $prioNumCurInterviewer +=  $cur->anzInterviewsOderPausenHintereinander($day,$slotOfTheDay,true)*30;//TODO ;

            /** Einfügen der PrioNummer und des Interviewers in die Passenden Heaps */

            //wenn derjenige unerfahren ist -> Nur Protokollieren möglich und dort Bonus
            if ($cur->istUnerfahren) {
                //bonus, damit die Unerfahrenen möglichst schnell Interviewer sind:
                $prioNumCurInterviewer += 40; //TODO Faktor

                //je nach geschlecht in 8 oder 9 einteilen
                /** @var PairHeap $tempPairHeap */
                $tempPairHeap = $interviewerHeaps[$cur->weiblich ? 8 : 9];
                $tempPairHeap->insert(new Pair($prioNumCurInterviewer, $cur));
            } // semiErfahren -> nur ZweitInterviews
            else if ($cur->istSemiErfahren) {
                //bonus, damit möglichst schnell Erfahren:
                $prioNumCurInterviewer += 40; //TODO Faktor

                //StudienGang gleicht -> 4 oder 6 je nach Geschlecht, nicht gleich -> 5 oder 7
                $num = ($cur->studienrichtungID == $interviewee->studienrichtungID || ($cur->istITler && $interviewee->istITler )) ? 4 : 5;
                if (!$cur->weiblich)
                    $num += 2;

                /** @var PairHeap $tempPairHeap */
                $tempPairHeap = $interviewerHeaps[$num];
                $tempPairHeap->insert(new Pair($prioNumCurInterviewer, $cur));
            } //erfahren
            else {

                //wir müssen ausgleich schaffen, sodass gesamt möglicht alle Jobs gleich viel gemacht werden
                $minAnzInterviewType = min($cur->anzErstInterviews, $cur->anzZweitInterviews, $cur->anzProtokollant);
                $anzArray = collect([$cur->anzErstInterviews, $cur->anzZweitInterviews, $cur->anzProtokollant]);
                $faktorAusgewogenheit = 0; //TODO Faktor


                $startNum = $cur->weiblich ? 0 : 2;
                if ($cur->studienrichtungID != $interviewee->studienrichtungID && !($cur->istITler && $interviewee->istITler))
                    $startNum++;


                $loopCounter = 0;
                for ($i = $startNum; $i < 8; $i += 4) {

                    /** @var PairHeap $tempPairHeap */
                    $tempPairHeap = $interviewerHeaps[$i];
                    //Ausgewogenheit auf die PrioNum mit drauf rechnen
                    $tempPairHeap->insert(new Pair($prioNumCurInterviewer - (($anzArray[$loopCounter] - $minAnzInterviewType) * $faktorAusgewogenheit), $cur));

                    $loopCounter++;
                }

                //protokoll noch einfügen
                /** @var PairHeap $tempPairHeap */
                $tempPairHeap = $interviewerHeaps[$cur->weiblich ? 8 : 9];
                $tempPairHeap->insert(new Pair($prioNumCurInterviewer - (($anzArray[2] - $minAnzInterviewType) * $faktorAusgewogenheit), $cur));
           // }
        }
    }

}
