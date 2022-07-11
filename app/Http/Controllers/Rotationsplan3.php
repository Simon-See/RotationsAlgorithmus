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

        $collection = $this->testRotationsPlanErstellen();


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

        $interviewerInnen = $this->createInterviewerORInterviewees($pathToInterviewer, true, $metaDaten[0], $metaDaten[1], $metaDaten[2]);

        $arrayOfIntererviewees = $this->createInterviewerORInterviewees($pathToInterviewees, false, $metaDaten[0], $metaDaten[1], $metaDaten[2]);

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
                #print("<br>arr: " . implode($arr));
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
        for ($day = 0; $day < count($anzInterviewsTagX); $day++) {
            //1st Dimension
            $zeitSlotArray[] = collect([]);
            for ($slot = 0; $slot < $anzInterviewsTagX[$day]; $slot++) {
                //2nd Dimension
                $zeitSlotArray[$day][] = collect([]);
                for ($roomNum = 0; $roomNum < $anzRaeume; $roomNum++) {
                    //3rd Dimension
                    $zeitSlotArray[$day][$slot][] = new Zeitslot($day, $slot, $roomNum);
                    $anzSlotsGesamt++;
                }
            }
        }


        /** Über das ZeitslotArray iterieren und alle Zeitslots befüllen */

        //iterieren über die einzelnen Tage
        for ($day = 0; $day < count($anzInterviewsTagX); $day++) {

            //resetten der anz Interviews am aktuellen Tag bei allen Interviewern
            for ($h = 0; $h < count($interviewer); $h++) {
                /** @var Interviewer $curInterviewer */
                $curInterviewer = $interviewer[$h];
                $curInterviewer->anzInterviewsAnAktuellenTag = 0;
            }


            /**iterieren über die einzelnen Zeiten*/
            for ($slot = 0; $slot < $anzInterviewsTagX[$day]; $slot++) {


                //für die Zeit für jeden Interviewer seine priority Num berechnen
                $interviewerHeaps = collect([new PairHeap(), new PairHeap(), new PairHeap(), new PairHeap(), new PairHeap(), new PairHeap()]);


                for ($interviewerID = 0; $interviewerID < count($interviewer); $interviewerID++) {
                    $this->insertingCurInterviewerIntoTheHeaps($interviewer[$interviewerID], $day, $slot, $anzInterviewsAvg, $interviewerHeaps, $anzSlotsGesamt);
                }
                //TODO PairHeaps durch Array und sort austauschen

                print("<br>InterviewerHeaps: <br>");
                for ($i = 0; $i < count($interviewerHeaps); $i++) {
                    print("Heap: " . $i . ": " . $interviewerHeaps[$i]);
                }

                print("<br>");


                /**iterieren über die Räume*/
                for ($roomNum = 0; $roomNum < $anzRaeume; $roomNum++) {
                    print("CurrentSlot+Room:[" . $day . "][" . $slot . "][" . $roomNum . "]");

                    //print("RoomNum: " . $roomNum . "<br>");

                    //nehme die 3 besten die nicht in einem anderen Raum tätig schaue so, dass diese nicht alle Weiblich und nicht alle männlich
                    $besteKombinationen = collect([]);

                    //entferne Leute die in anderem Raum tätig
                    for ($heapNr = 0; $heapNr < count($interviewerHeaps); $heapNr++) {
                        while (true) {
                            /** @var PairHeap $curPairHeap */
                            $curPairHeap = $interviewerHeaps[$heapNr];

                            //print("Heap nr. " . $heapNr  . "<br> " . $curPairHeap);

                            if ($curPairHeap->isEmpty())
                                break;

                            /**@var Pair $curPair */
                            $curPair = $curPairHeap->top();
                            //wenn er schon in anderem Raum tätig -> entfernen des Interviewers ansonsten passt es bei dem Heap
                            if ($curPair->interviewer->bolZugewieseneZeitslots[$day][$slot]){
                                print("<br> extracted: " . $curPair->interviewer);
                                $curPairHeap->extract();
                            }
                            else
                                break;
                        }
                    }

                    print("<br>InterviewerHeapsDanach: <br>");
                    for ($i = 0; $i < count($interviewerHeaps); $i++) {
                        print("Heap: " . $i . ": " . $interviewerHeaps[$i]);
                    }


                    print("<br> Erstelle Mögliche Kombinationen: <br>");

                    //erstelle die besten Kombinationen:
                    for ($erstHeaps = 0; $erstHeaps < 2; $erstHeaps++) {

                        /** @var PairHeap $erstIntHeap */
                        $erstIntHeap = $interviewerHeaps[$erstHeaps];
                        if ($erstIntHeap->isEmpty())
                            continue;
                        /** @var Pair $erstInt */
                        $erstInt = $erstIntHeap->top();

                        //Zweit Interviewer bestimmen
                        for ($zweitHeaps = 0; $zweitHeaps < 2; $zweitHeaps++) {

                            /** @var PairHeap $zweitIntHeap */
                            $zweitIntHeap = $interviewerHeaps[$zweitHeaps+2];
                            if ($zweitIntHeap->isEmpty())
                                continue;
                            /** @var Pair $zweitInt */
                            $zweitInt = $zweitIntHeap->top();

                            for ($drittHeaps = 0; $drittHeaps < 2; $drittHeaps++) {

                                print("<br> erst Und Zweit: " . $erstInt . " " . $zweitInt);
                                //wenn alle 3 weiblich oder männlich sind -> continue
                                if ($erstHeaps == $zweitHeaps && $zweitHeaps == $drittHeaps) {

                                    continue;
                                }

                                //wenn erst und zweit Interviewer ITler sind -> continue
                                if ($erstInt->interviewer->istITler && $zweitInt->interviewer->istITler)
                                    continue;

                                /** @var PairHeap $drittIntHeap */
                                $drittIntHeap = $interviewerHeaps[$drittHeaps+4];

                                if ($drittIntHeap->isEmpty())
                                    continue;

                                /** @var Pair $drittInt */
                                $drittInt = $drittIntHeap->top();

                                //kombination hinzufügen:
                                $besteKombinationen[] = new InterviewerCombination($erstInt, $zweitInt, $drittInt);
                            }
                        }
                    }
                    //wenn die Kombinationen leer sind zu diesem Zeitpunkt -> nächster Zeitpunkt
                    if ($besteKombinationen->isEmpty())
                    {
                        print("BesteCombinationIsEmpty: [" . $day . "][" . $slot . "][" . $roomNum . "]" );
                        continue;
                    }

                    //print(" still in RoomNum: " . $roomNum . "<br>");

                    /** Den besten Interviewer mit der besten Kombinationen ausrechnen */

                    $ausgewaehlteKombination = new InterviewerCombination(new Pair(-1, new Interviewer(-1, "", false, false, false, false, false, -1, collect([]), collect([]))), new Pair(-1, new Interviewer(-1, "", false, false, false, false, false, -1, collect([]), collect([]))), new Pair(-1, new Interviewer(-1, "", false, false, false, false, false, -1, collect([]), collect([]))));
                    $prioNumAusgewaehlteKombination = -10000;
                    $bestInterviewee = new Interviewee("", false, false, -1, collect([]));
                    $bolSameStudiengang = false;
                    //ist true wenn sowohl bei (erst/zweit) Interviewern kein Itler dabei und der Interviewee auch kein Itler ist oder bei beiden kein Itler
                    $GenauDannWennITler = false;
                    $anzFreeInterviewSlots = 10000000;


                    //alle Interviewer und besten auswählen
                    for ($id = 0; $id < count($arrayOfIntererviewees); $id++) {
                        /** @var Interviewee $curInterviewee */
                        $curInterviewee = $arrayOfIntererviewees[$id];

                        if ($curInterviewee->zeitSlotBereitsZugewiesen)
                            continue;


                        //Alle Kombinationen durchgehen und schauen ob man die derzeit beste ersetzen sollte
                        for ($comb = 0; $comb < count($besteKombinationen); $comb++) {
                            /** @var InterviewerCombination $betrachteteKombination */
                            $betrachteteKombination = $besteKombinationen[$comb];
                            //wir betrachten das ganze nur wenn GDW ITler sich auf true verbessert oder gleich bleibt
                            if (($curInterviewee->istITler == $betrachteteKombination->hatItler()) || !$betrachteteKombination) {

                                //es soll sich auch der SameStudiengang entweder verbessern oder gleich bleiben ODER der Interviewee kann nur noch diesen Slot
                                if ($betrachteteKombination->isSameStudiengang($curInterviewee->studienrichtungID) || !$bolSameStudiengang || $curInterviewee->wieVieleZeitslotsKannPersonNoch($day, $slot) <= 1) {

                                    //schauen ob sich verbessert wurde ODER gleich geblieben ist dann nehmen wir den der weniger Slots noch kann:
                                    if ($betrachteteKombination->prioirityNumber > $prioNumAusgewaehlteKombination || ($betrachteteKombination->prioirityNumber == $prioNumAusgewaehlteKombination && $anzFreeInterviewSlots < $curInterviewee->wieVieleZeitslotsKannPersonNoch($day, $slot))) {
                                        //Werte Updaten:
                                        $ausgewaehlteKombination = $betrachteteKombination;
                                        $prioNumAusgewaehlteKombination = $betrachteteKombination->prioirityNumber;
                                        $bestInterviewee = $curInterviewee;
                                        $bolSameStudiengang = $betrachteteKombination->isSameStudiengang($curInterviewee->studienrichtungID);
                                        $GenauDannWennITler = ($curInterviewee->istITler == $betrachteteKombination->hatItler());
                                        $anzFreeInterviewSlots = $curInterviewee->wieVieleZeitslotsKannPersonNoch($day, $slot);
                                    }
                                }
                            }
                        }
                    }

                    //Nun haben wir eine Beste Kombination und füllen diese in das ZeitslotArray
                    $newZeitslot = new Zeitslot($day, $slot, $roomNum);
                    $newZeitslot->interviewerCombination = $ausgewaehlteKombination;
                    $newZeitslot->interviewee = $bestInterviewee;
                    $zeitSlotArray[$day][$slot][$roomNum] = $newZeitslot;
                    print("ZeitslotArray[" . $day . "][" . $slot . "][" . $roomNum . "] = " . ($zeitSlotArray[$day][$slot][$roomNum]));
                    // wenn Null value eingetragen wurde -> continue
                    if ($anzFreeInterviewSlots == 10000000)
                        continue;


                    // Dinge updaten (kann bei Zeitslot, anz Interviews, erfahren etc)
                    $bestInterviewee->zeitSlotBereitsZugewiesen = true;
                    $ausgewaehlteKombination->erst->interviewer->anzErstInterviews++;
                    $ausgewaehlteKombination->zweit->interviewer->anzZweitInterviews++;
                    $ausgewaehlteKombination->protokoll->interviewer->anzProtokollant++;

                    for ($l = 0; $l < count($ausgewaehlteKombination->interviewerArr); $l++) {
                        /** @var Pair $tempInterviewer */
                        $tempInterviewer = $ausgewaehlteKombination->interviewerArr[$l];
                        $tempInterviewer->interviewer->bolZugewieseneZeitslots[$day][$slot] = true;
                        $tempInterviewer->interviewer->anzInterviewsAnAktuellenTag++;
                        $tempInterviewer->interviewer->erfahrungsStufeUpdaten();
                    }




                    //TODO HERAUSFINDEN WARUM ICH DEN VIERTEN RAUM NICHT VOLL KRIEGE
                }
            }
        }

        for ($day = 0; $day < count($anzInterviewsTagX); $day++) {
            print("Day " . $day . "<br>");
            print("Slot;Raum;Interviewee;Erstinterviewer;Zweitinterviewer;Protokollant<br>");
            for ($slot = 0; $slot < $anzInterviewsTagX[$day]; $slot++) {
                for ($room = 0; $room < $anzRaeume; $room++) {
                    print("Day: " . $day . " slot: " . $slot . " room: " . $room);
                    print($slot . ";" . $room . ";" . $zeitSlotArray[$day][$slot][$room]);
                }
            }
            print("<br><br><br>");
        }

        return $zeitSlotArray;
    }


    function insertingCurInterviewerIntoTheHeaps(Interviewer $cur, int $day, int $slotOfTheDay, float $anzInterviewsAVG, Collection $interviewerHeaps, int $anzSlotsGesamt): void
    {

        //print("<br>Cur Interviewer: " . $cur);
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

        //wenn die Person noch kein Interview hatte heute -> ungern eins
        $prioNumCurInterviewer += $cur->anzInterviewsOderPausenHintereinander($day, $slotOfTheDay, true) * 30;//TODO ;

        /** Einfügen der PrioNummer und des Interviewers in die Passenden Heaps */

        //wenn derjenige unerfahren ist -> Nur Protokollieren möglich und dort Bonus
        if ($cur->istUnerfahren) {
            //bonus, damit die Unerfahrenen möglichst schnell Interviewer sind:
            $prioNumCurInterviewer += 60; //TODO Faktor

            //je nach geschlecht in 4 oder 5 einteilen
            /** @var PairHeap $tempPairHeap */
            $tempPairHeap = $interviewerHeaps[$cur->weiblich ? 4 : 5];
            $tempPairHeap->insert(new Pair($prioNumCurInterviewer, $cur));
            //print(" Inserted into: 4/5");
        } // semiErfahren -> nur ZweitInterviews
        else if ($cur->istSemiErfahren) {
            //bonus, damit möglichst schnell Erfahren:
            $prioNumCurInterviewer += 60; //TODO Faktor

            //je nach geschlecht in 2 oder 3 einteilen
            /** @var PairHeap $tempPairHeap */
            $tempPairHeap = $interviewerHeaps[$cur->weiblich ? 2 : 3];
            $tempPairHeap->insert(new Pair($prioNumCurInterviewer, $cur));
            //print(" Inserted into: 2/3");
        } //erfahren
        else {

            //TODO tracken ob ganz am Anfang erfahren etc. -> bestimmte verteilung erreichen z.B. 4 erst  2 zweit  2 protokollS
            $startNum = $cur->weiblich ? 0 : 1;

            //print(" Inserted into all of them");
            for ($i = 0; $i < 3; $i++) {

                //je nach geschlecht in 0,2,4 oder 1,3,5 einteilen
                /** @var PairHeap $tempPairHeap */
                $tempPairHeap = $interviewerHeaps[$startNum + ($i * 2)];
                $tempPairHeap->insert(new Pair($prioNumCurInterviewer, $cur));
                //print("<br>Inserted " . $cur . "  Into: " . $tempPairHeap);
            }
        }
    }

}
