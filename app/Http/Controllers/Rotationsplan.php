<?php

namespace App\Http\Controllers;

use Illuminate\Support\Collection;
use LimitIterator;

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
        #//print("MetaDaten");
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
                #//print("<br>arr: " . implode($arr));
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

                ////print("TestArray: " . $testArr);

                /*//print("<br>InterviewerHeaps: <br>");
                for ($i = 0; $i < count($interviewerHeaps); $i++) {
                    //print("Heap: " . $i . ": " . $interviewerHeaps[$i]);
                }

                //print("<br>");
*/

                /**iterieren über die Räume*/
                for ($roomNum = 0; $roomNum < $anzRaeume; $roomNum++) {
                    ////print("CurrentSlot+Room:[" . $day . "][" . $slot . "][" . $roomNum . "]");

                    ////print("RoomNum: " . $roomNum . "<br>");

                    //nehme die 3 besten die nicht in einem anderen Raum tätig schaue so, dass diese nicht alle Weiblich und nicht alle männlich
                    $besteKombinationen = collect([]);

                    $bestInterviewersArr = collect([collect([]), collect(), collect(), collect(), collect(), collect()]);


                    /** entferne Leute die in anderem Raum tätig und ändere Heap zur Liste */
                    for ($heapNr = 0; $heapNr < count($interviewerHeaps); $heapNr++) {
                        while (true) {
                            /** @var PairHeap $curPairHeap */
                            $curPairHeap = $interviewerHeaps[$heapNr];
                            if ($curPairHeap->isEmpty())
                                break;

                            /**@var Pair $curPair */
                            $curPair = $curPairHeap->extract();
                            //wenn noch nicht in anderem Raum tätig -> in Array einfügen
                            if (!$curPair->interviewer->bolZugewieseneZeitslots[$day][$slot]) {
                                $bestInterviewersArr[$heapNr][] = $curPair;
                            }
                        }
                    }


                    ////print("<br>InterviewerHeapsDanach: <br>");
                    /*
                                        for ($i = 0; $i < count($interviewerHeaps); $i++) {
                                            //print("<br>: " . $i . ": ");
                                            for ($j = 0; $j < count($bestInterviewersArr[$i]); $j++) {
                                                //print($bestInterviewersArr[$i][$j] . ", ");
                                            }
                                        }


                                        //print("<br> Erstelle Mögliche Kombinationen: <br>");
                    */
                    /** gehe für jedes interviewer Array alle möglichkeiten durch und füge sie der Priorityque an */
                    for ($erstArr = 0; $erstArr < 2; $erstArr++) {
                        for ($stelleImErstenArr = 0; $stelleImErstenArr < count($bestInterviewersArr[$erstArr]); $stelleImErstenArr++) {
                            /** @var Pair $erstInt */
                            $erstInt = $bestInterviewersArr[$erstArr][$stelleImErstenArr];

                            ////print("<br>" . $erstInt->interviewer->name);
                            //Zweit Interviewer bestimmen
                            for ($zweitArr = 2; $zweitArr < 4; $zweitArr++) {
                                for ($stelleImZweitenArr = 0; $stelleImZweitenArr < count($bestInterviewersArr[$zweitArr]); $stelleImZweitenArr++) {
                                    /** @var Pair $zweitInt */
                                    $zweitInt = $bestInterviewersArr[$zweitArr][$stelleImZweitenArr];

                                    //wenn die gleiche Person gewählt wurde -> continue
                                    if ($erstInt->interviewer->id == $zweitInt->interviewer->id)
                                        continue;
                                    //wenn erst und zweit Interviewer ITler sind -> continue


                                    ////print("<br>.." . $zweitInt->interviewer->name);

                                    for ($drittArr = 4; $drittArr < 6; $drittArr++) {
                                        for ($stelleImDrittenArr = 0; $stelleImDrittenArr < count($bestInterviewersArr[$drittArr]); $stelleImDrittenArr++) {

                                            /** @var Pair $drittInt */
                                            $drittInt = $bestInterviewersArr[$drittArr][$stelleImDrittenArr];
                                            //wenn die gleiche Person gewählt wurde -> continue
                                            if ($erstInt->interviewer->id == $drittInt->interviewer->id || $zweitInt->interviewer->id == $drittInt->interviewer->id)
                                                continue;

                                            $combination = new InterviewerCombination($erstInt, $zweitInt, $drittInt);

                                            //wenn alle 3 weiblich oder männlich sind -> abzüge
                                            if (($erstArr % 2) == ($zweitArr % 2) && ($zweitArr % 2) == ($drittArr % 2)) {
                                                $combination->prioirityNumber = $combination->prioirityNumber / 2;
                                            }
                                            //wenn 2 ITler -> abzüge
                                            if ($erstInt->interviewer->istITler && $zweitInt->interviewer->istITler)
                                                $combination->prioirityNumber = $combination->prioirityNumber / 2;

                                            //kombination hinzufügen:
                                            $besteKombinationen[] = $combination;
                                            ////print(", len: " . count($besteKombinationen));
                                        }
                                    }
                                }
                            }
                        }
                    }
                    //wenn die Kombinationen leer sind zu diesem Zeitpunkt -> nächster Zeitpunkt
                    if (count($besteKombinationen) == 0) {
                        //print("BesteCombinationIsEmpty: [" . $day . "][" . $slot . "][" . $roomNum . "]");
                        continue;
                    }

                    ////print(" still in RoomNum: " . $roomNum . "<br>");

                    /** Den besten Interviewer mit der besten Kombinationen ausrechnen */
                    $ausgewaehlteKombination = new InterviewerCombination(new Pair(-1, new Interviewer(-1, "", false, false, false, false, false, -1, collect([]), collect([]))), new Pair(-1, new Interviewer(-1, "", false, false, false, false, false, -1, collect([]), collect([]))), new Pair(-1, new Interviewer(-1, "", false, false, false, false, false, -1, collect([]), collect([]))));
                    $prioNumAusgewaehlteKombination = -10000;
                    $bestInterviewee = new Interviewee("", false, false, -1, collect([]));
                    $bolSameStudiengang = false;
                    //ist true wenn sowohl bei (erst/zweit) Interviewern kein Itler dabei und der Interviewee auch kein Itler ist oder bei beiden kein Itler
                    $GenauDannWennITler = false;
                    $anzFreeInterviewSlots = 10000000;
                    $bolWasThereKombinationPickedAlready = false;

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
                            //Bonus WENN BEIDE ITLER:
                            $bonus = ($curInterviewee->istITler && $betrachteteKombination->hatItler()) ? 100 : 0; //TODO FAKTOR

                            //es soll sich auch der SameStudiengang entweder verbessern oder gleich bleiben ODER der Interviewee kann nur noch diesen Slot
                            if ($betrachteteKombination->isSameStudiengang($curInterviewee->studienrichtungID) || !$bolSameStudiengang || $curInterviewee->wieVieleZeitslotsKannPersonNoch($day, $slot) <= 1) {

                                $bolSameStudiengangIstBesserGeworden = ($betrachteteKombination->isSameStudiengang($curInterviewee->studienrichtungID) && !$bolSameStudiengang);

                                //schauen ob sich verbessert wurde ODER gleich geblieben ist dann nehmen wir den der weniger Slots noch kann:
                                if ($bolSameStudiengangIstBesserGeworden || $bonus + $betrachteteKombination->prioirityNumber > $prioNumAusgewaehlteKombination || ($betrachteteKombination->prioirityNumber == $prioNumAusgewaehlteKombination && $anzFreeInterviewSlots < $curInterviewee->wieVieleZeitslotsKannPersonNoch($day, $slot))) {
                                    //Werte Updaten:
                                    $ausgewaehlteKombination = $betrachteteKombination;
                                    $prioNumAusgewaehlteKombination = $betrachteteKombination->prioirityNumber + $bonus;
                                    $bestInterviewee = $curInterviewee;
                                    $bolSameStudiengang = $betrachteteKombination->isSameStudiengang($curInterviewee->studienrichtungID);
                                    $GenauDannWennITler = ($curInterviewee->istITler == $betrachteteKombination->hatItler());
                                    $anzFreeInterviewSlots = $curInterviewee->wieVieleZeitslotsKannPersonNoch($day, $slot);
                                    $bolWasThereKombinationPickedAlready = true;
                                }

                            }

                        }
                    }

                    //Nun haben wir eine Beste Kombination und füllen diese in das ZeitslotArray
                    $newZeitslot = new Zeitslot($day, $slot, $roomNum);
                    $newZeitslot->interviewerCombination = $ausgewaehlteKombination;
                    $newZeitslot->interviewee = $bestInterviewee;
                    $zeitSlotArray[$day][$slot][$roomNum] = $newZeitslot;
                    ////print("ZeitslotArray[" . $day . "][" . $slot . "][" . $roomNum . "] = " . ($zeitSlotArray[$day][$slot][$roomNum]));
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

                    //Put the array back into the heaps for the loop to work:
                    for ($s = 0; $s < 6; $s++) {
                        for ($arrIter = 0; $arrIter < count($bestInterviewersArr[$s]); $arrIter++) {
                            /** @var PairHeap $heapS */
                            $heapS = $interviewerHeaps[$s];
                            $heapS->insert($bestInterviewersArr[$s][$arrIter]);
                        }
                    }

                }
            }
        }


        ////print it and put it into array:

        $csvFile = fopen('RotationsPlan.csv','w');

        /** Alle Interviewees die in keinem Zeitslot vorgekommen sind sammeln */
        $intervieweesNichtInZeitslot = collect([]);

        for ($i = 0; $i < count($arrayOfIntererviewees); $i++) {

            /** @var Interviewee $cur */
            $cur = $arrayOfIntererviewees[$i];
            if (!$cur->zeitSlotBereitsZugewiesen) {
                $intervieweesNichtInZeitslot->add($cur);
                //print("Nicht Zugewiesen: " . $cur . "<br>");
            }
        }

        fputcsv($csvFile,["Alle Interviewees haben Slot:" , ((count($intervieweesNichtInZeitslot)== 0)?"Yes":"Nein") ]);


        for ($day = 0; $day < count($anzInterviewsTagX); $day++) {
            //print("Day " . $day . "<br>");


            //print("Slot;Raum;Interviewee;Erstinterviewer;Zweitinterviewer;Protokollant;KeinInterviewerSameStudiengang<br>");
            fputcsv($csvFile,[""]);
            fputcsv($csvFile,[""]);
            fputcsv($csvFile,["_____DAY " . $day . "_____"]);
            fputcsv($csvFile,["Slot","Raum","Interviewee","Erstinterviewer","Zweitinterviewer","Protokollant","SameStudiengang"]);


            for ($slot = 0; $slot < $anzInterviewsTagX[$day]; $slot++) {
                for ($room = 0; $room < $anzRaeume; $room++) {


                   ////print("Day: " . $day . " slot: " . $slot . " room: " . $room);
                    ////print($slot . ";". $room. ";");
                    if ($zeitSlotArray[$day][$slot][$room] != null){
                        ////print($zeitSlotArray[$day][$slot][$room]);
                        //$csvArray[] = [$slot,$room,$zeitSlotArray[$day][$slot][$room]];
                        fputcsv($csvFile,[$slot,$room,$zeitSlotArray[$day][$slot][$room]]);
                    }
                    ////print("<br>");

                }
            }
            //print("<br><br><br>");


        }


        /** wie oft war jeder Interviewer dran:*/
        //print("<br> Anzahl Inteviews von den Einzelnen Interviewern<br>");
        fputcsv($csvFile,[""]);
        fputcsv($csvFile,[""]);

        fputcsv($csvFile,['"Interviewer"',"anzahl Gesamt","anzahl ErstInterviews","anzahl ZweitInterviews","anzahl Protokoll","ErfahrungsGrad"]);

        for ($i = 0; $i < count($interviewer); $i++) {
            /** @var Interviewer $curI */
            $curI = $interviewer[$i];
            $erfahrungsGrad = $curI->istUrspruenglichErfahren ? "Erfahren" : ($curI->istUrspruenglichSemiErfahren ? "SemiErfahren" : "Unerfahren");
            fputcsv($csvFile,[$curI->name,$curI->anzErstInterviews+$curI->anzZweitInterviews+$curI->anzProtokollant ,$curI->anzErstInterviews,$curI->anzZweitInterviews,$curI->anzProtokollant, $erfahrungsGrad]);

            //print("<br>" . "erst; " . $curI->anzErstInterviews . "; zweit; " . $curI->anzZweitInterviews . "; dritt; " . $curI->anzProtokollant . "; gesamt; " . ($curI->anzErstInterviews + $curI->anzZweitInterviews + $curI->anzProtokollant) . "; " . $curI->name);
        }


        //replace all " in the csv file with blanks
        $csvContent = file_get_contents("RotationsPlan.csv");
        $csvContent = str_replace('"','',$csvContent);
        $csvContent = str_replace(',',';',$csvContent);
        file_put_contents("RotationsPlan.csv",$csvContent);

        //TODO abklären, ob protokollant gleicher Studiengang reicht
        //TODO und wie die "Optimale verteilung für Erfahrene / Semierfahrene und Unerfahrene ist
        //TODO evtl ein Bonus wenn es die gleiche StudienID ist und diese Selten ist
        fclose($csvFile);
        return $zeitSlotArray;
    }


    function insertingCurInterviewerIntoTheHeaps(Interviewer $cur, int $day, int $slotOfTheDay, float $anzInterviewsAVG, Collection $interviewerHeaps, int $anzSlotsGesamt): void
    {



        //wenn jemand unerfahren ist, aber schon lange im Verein -> eher 3 Hauptinterviews als jemand anderes



        ////print("<br>Cur Interviewer: " . $cur);
        //desto höher desto Besser
        $prioNumCurInterviewer = 0;
        $prioAdditonProtokoll = 0;
        $prioAdditonErst = 0;
        $prioAdditonZweit = 0;


        $faktorEinhaltungDerVerteilung = 40;//TODO Faktor
        /** Je nachdem was die Interviewer am Anfang sind, sollen sie verschiedene Verteilungen erreichen */
        //ursprünglichErfahrenen ~2 Protokollant ~4 erstinterviews ~3 zweit
        if($cur->istUrspruenglichErfahren){

            //Für jedes Protokoll mehr als 2: Abzug, Für jedes weniger als 2: Bonus
            $prioAdditonProtokoll += ((-$cur->anzProtokollant)+2) * $faktorEinhaltungDerVerteilung;

            $prioAdditonZweit += ((-$cur->anzZweitInterviews) + 3) *$faktorEinhaltungDerVerteilung;

            $prioAdditonErst +=  ((-$cur->anzErstInterviews) + 4) *$faktorEinhaltungDerVerteilung;
        }
        //ursprünglichSemierfahren: 3 Protokoll, 3 erst, 3 zweit
        else if($cur->istUrspruenglichSemiErfahren){
            $prioAdditonProtokoll += ((-$cur->anzProtokollant)+3) * $faktorEinhaltungDerVerteilung;

            $prioAdditonZweit += ((-$cur->anzZweitInterviews) + 3) *$faktorEinhaltungDerVerteilung;

            $prioAdditonErst +=  ((-$cur->anzErstInterviews) + 3) *$faktorEinhaltungDerVerteilung;
        }
        //ursprünglichUnerfahren: 4 Protokoll, 2 erst, 3 zweit
        else if($cur->istUrspruenglichUnerfahren){
            $prioAdditonProtokoll += ((-$cur->anzProtokollant)+4) * $faktorEinhaltungDerVerteilung;

            $prioAdditonZweit += ((-$cur->anzZweitInterviews) + 3) *$faktorEinhaltungDerVerteilung;

            $prioAdditonErst +=  ((-$cur->anzErstInterviews) + 2) *$faktorEinhaltungDerVerteilung;

        }



        //Die Person kann zu dem Zeitpunkt nicht:
        if (!$cur->kannBeiZeitslot[$day][$slotOfTheDay] || $cur->bolZugewieseneZeitslots[$day][$slotOfTheDay])
            return;

        //Desto weniger Zeitslots die Person noch kann desto höher die Prio
        $prioNumCurInterviewer += ($anzSlotsGesamt - $cur->wieVieleZeitslotsKannPersonNoch($day, $slotOfTheDay)) * 10; //TODO FAKTOR

        //man möchte in the long run möglichst nah ans Average ran -> wem noch einiges Fehlt bekommt bonus, wer drüber ist Abzüge
        $prioNumCurInterviewer -= (((float)$cur->wieVielInterviewsBereitsGehabt()) - $anzInterviewsAVG) * 70; //TODO FAKTOR
        ////print("<br> avg:".(-(($cur->wieVielInterviewsBereitsGehabt()) - $anzInterviewsAVG) * 100));

        //wenn die Person noch kein Interview hatte heute -> ungern eins
        $prioNumCurInterviewer += $cur->anzInterviewsOderPausenHintereinander($day, $slotOfTheDay, true) * 30;//TODO ;

        /** Einfügen der PrioNummer und des Interviewers in die Passenden Heaps */

        //wenn derjenige unerfahren ist -> Nur Protokollieren möglich und dort Bonus
        if ($cur->istUnerfahren) {
            //bonus, damit die Unerfahrenen möglichst schnell Interviewer sind:
            $prioNumCurInterviewer += 60; //TODO Faktor
            $prioNumCurInterviewer += $prioAdditonProtokoll;
            //je nach geschlecht in 4 oder 5 einteilen
            /** @var PairHeap $tempPairHeap */
            $tempPairHeap = $interviewerHeaps[$cur->weiblich ? 4 : 5];
            $tempPairHeap->insert(new Pair($prioNumCurInterviewer, $cur));
            ////print(" Inserted into: 4/5");
        } // semiErfahren -> nur ZweitInterviews
        else if ($cur->istSemiErfahren) {
            //bonus, damit möglichst schnell Erfahren:
            $prioNumCurInterviewer += 60; //TODO Faktor
            $prioNumCurInterviewer += $prioAdditonZweit;
            //je nach geschlecht in 2 oder 3 einteilen
            /** @var PairHeap $tempPairHeap */
            $tempPairHeap = $interviewerHeaps[$cur->weiblich ? 2 : 3];
            $tempPairHeap->insert(new Pair($prioNumCurInterviewer, $cur));
            ////print(" Inserted into: 2/3");
        } //erfahren
        else {


            $arrWithAdditions = collect([$prioAdditonErst,$prioAdditonZweit,$prioAdditonProtokoll]);

            $startNum = $cur->weiblich ? 0 : 1;
            for ($i = 0; $i < 3; $i++) {



                //je nach geschlecht in 0,2,4 oder 1,3,5 einteilen
                /** @var PairHeap $tempPairHeap */
                $tempPairHeap = $interviewerHeaps[$startNum + ($i * 2)];
                $tempPairHeap->insert(new Pair($prioNumCurInterviewer +$arrWithAdditions[$i] , $cur));
                ////print("<br>Inserted " . $cur . "  Into: " . $tempPairHeap);
            }
        }
    }

}
