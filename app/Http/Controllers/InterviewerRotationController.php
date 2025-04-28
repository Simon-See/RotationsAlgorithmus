<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\In;
use InvalidArgumentException;
use SplEnum;
use SplPriorityQueue;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InterviewerRotationController extends Controller
{
    public function index()
    {
        return view('interviewer-rotation');
    }

    public function upload(Request $request, $type)
    {
        $request->validate([
            $type => 'required|file|mimes:csv,txt|max:2048',
        ]);

        if ($request->hasFile($type)) {
            $file = $request->file($type);

            // Read the CSV file with semicolon delimiter
            $handle = fopen($file->path(), "r");
            $csvData = [];
            while (($row = fgetcsv($handle, 0, ";")) !== false) {
                $csvData[] = $row;
            }
            fclose($handle);

            // Define the expected header and data types based on the type
            if ($type === 'applicants') {
                $expectedHeader = ['Vorname', 'Nachname', 'IstTechie', 'IstWeiblich', 'Freitag', 'Samstag', 'Sonntag', 'StudiengangsrichtungID', 'Wiederbewerber'];
                $expectedDataTypes = ['string', 'string', 'boolean', 'boolean', 'boolean', 'boolean', 'boolean', 'integer', 'boolean'];
            } elseif ($type === 'interviewers') {
                $expectedHeader = ['Vorname', 'Nachname', 'IstTechie', 'IstWeiblich', 'Freitag', 'Samstag', 'Sonntag', 'StudiengangsrichtungID', 'ErfahrungsGrad'];
                $expectedDataTypes = ['string', 'string', 'boolean', 'boolean', 'boolean', 'boolean', 'boolean', 'integer', 'integer'];
            } else {
                // Invalid type
                return redirect()->back()->with('error', 'Invalid upload type.');
            }


            // Validate the header
            if ($csvData[0] != $expectedHeader) {
                $missingColumns = array_diff($expectedHeader, $csvData[0]);
                $extraColumns = array_diff($csvData[0], $expectedHeader);
                $error = '';

                if (!empty($missingColumns)) {
                    $error .= 'Missing columns: ' . implode(', ', $missingColumns) . '. ';
                }
                if (!empty($extraColumns)) {
                    $error .= 'Extra columns: ' . implode(', ', $extraColumns) . '. ';
                }
                return redirect()->back()->with('error', 'Invalid header in ' . $type . '.csv. ' . $error);
            }

            // Validate each row
            foreach ($csvData as $index => $row) {
                if ($index == 0) {
                    continue; // Skip the header row
                }
                if (count($row) != count($expectedHeader)) {
                    return redirect()->back()->with('error', 'Invalid row in' . $type . '.csv at line ' . ($index + 1));
                }
                foreach ($row as $key => $value) {
                    if ($key == 7) { // StudiengangsrichtungID
                        if (!is_numeric($value)) {
                            return redirect()->back()->with('error', 'Invalid data type for StudiengangsrichtungID in ' . $type . '.csv at line ' . ($index + 1));
                        }
                    } else {
                        $expectedDataType = $expectedDataTypes[$key];
                        if ($expectedDataType == 'boolean') {
                            if (!in_array($value, ['0', '1'])) {
                                return redirect()->back()->with('error', 'Invalid data type for ' . $expectedHeader[$key] . ' in ' . $type . '.csv at line ' . ($index + 1));
                            }
                        } elseif ($expectedDataType == 'integer') {
                            if (!ctype_digit($value)) {
                                return redirect()->back()->with('error', 'Invalid data type for ' . $expectedHeader[$key] . ' in ' . $type . '.csv at line ' . ($index + 1));
                            }
                        } elseif ($expectedDataType == 'string') {
                            // No validation needed for string data type
                        }
                    }
                }
            }


            $fileName = $type . '.csv';    //->getClientOriginalExtension();
            $file->storeAs('public', $fileName); // Store the file in storage/app/uploads

            // Process the uploaded file (e.g., parse CSV data, save to database)
            return redirect()->back()->with('success', ucfirst($type) . ' uploaded successfully.');
        }

        return redirect()->back()->with('error', 'File upload failed.');
    }

    public function downloadTemplate($type)
    {
        $fileName = $type === 'interviewers' ? 'interviewersTemplate.csv' : 'ApplicantsTemplate.csv';
        $filePath = storage_path('app/public/' . $fileName);

        if (!Storage::exists('public/' . $fileName)) {
            abort(404, 'Template file not found');
        }

        return response()->download($filePath, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function calculateRotation(Request $request)
    {
        Log::info("test");
        // Get the Data
        $sliderValues = [];
        for ($i = 1; $i <= 16; $i++) {
            $sliderValues[] = $request->input('sliderValue' . $i);
        }
        Log::info("initializing SliderValues:");
        SliderValues::initialize($sliderValues);

        $anzRaeume = $request->input('anzRaeume');
        $anzSlotsFreitag = $request->input('anzSlotsFreitag');
        $anzSlotsSamstag = $request->input('anzSlotsSamstag');
        $anzSlotsSonntag = $request->input('anzSlotsSonntag');

        $applicantsData = $this->readCSV('applicants.csv');
        $interviewersData = $this->readCSV('interviewers.csv');

        /**
         * @var Applicant[] $applicantsArr
         */
        $applicantsArr = [];
        foreach ($applicantsData as $index => $applicantData) {
            $applicantsArr[] = new Applicant($applicantData);
        }
        /**
         * @var Interviewer[] $interviewersArr
         */
        $interviewersArr = [];
        foreach ($interviewersData as $index => $interviewerData) {
            $interviewersArr[] = new Interviewer($interviewerData, $anzSlotsFreitag + $anzSlotsSamstag + $anzSlotsSonntag);
        }


        /**
         * @var Interview[] $allInterviewCombinations
         */
        $allInterviewCombinations = [];
        for ($i = 0; $i < count($applicantsArr); $i++) {
            for ($j = 0; $j < count($interviewersArr); $j++) {
                for ($k = 0; $k < count($interviewersArr); $k++) {
                    if ($j == $k) {
                        continue;
                    }
                    for ($l = 0; $l < count($interviewersArr); $l++) {
                        if ($l == $k or $j == $l) {
                            continue;
                        }
                        $allInterviewCombinations[] = new Interview($applicantsArr[$i], $interviewersArr[$j], $interviewersArr[$k], $interviewersArr[$l]);
                    }
                }
            }
        }
        Log::info("Groesse InterviewCombinationsArray: " . count($allInterviewCombinations));
        if (count($allInterviewCombinations) == 0) {
            return response()->json(['error' => 'Not enough Interviewers.'], 400);
        }

        /**
         * @var Interview[][] $resultingArray
         */
        $resultingArray = $this->fillResultingArrayGreedy($allInterviewCombinations,$interviewersArr,$anzSlotsFreitag,$anzSlotsSamstag,$anzSlotsSonntag, $anzRaeume);


        $csvPath = storage_path('app\public\RotationPlan.csv');
        $csvFile = fopen($csvPath, 'w');

        $day = 0;
        fputcsv($csvFile, ["Day: " . $day], ';');
        fputcsv($csvFile, ["Slot", "Raum", "Bewerber", "StudiengangId", "Erstinterviewer", "StudiengangId", "Zweitinterviewer", "StudiengangId", "Protokollant", "StudiengangId"], ';');
        $numInterviewer = count($interviewersArr);
        $counterSlots = 0;
        //if (($anzSlotsFreitag + $anzSlotsSamstag + $anzSlotsSonntag) !=count($resultingArray) )
        //    throw new InvalidArgumentException("resultingArray not correct size");

        foreach ($resultingArray as $i => $resultingArraySingleSlot) {
            if ($counterSlots== $anzSlotsFreitag) {
                $day = 1;
                fputcsv($csvFile, [""], ';');
                fputcsv($csvFile, ["Day: " . $day], ';');
                fputcsv($csvFile, ["Slot", "Raum", "Bewerber", "StudiengangId", "Erstinterviewer", "StudiengangId", "Zweitinterviewer", "StudiengangId", "Protokollant", "StudiengangId"], ';');
            }
            if ($counterSlots == $anzSlotsSamstag+$anzSlotsFreitag) {
                $day = 2;
                fputcsv($csvFile, [""], ';');
                fputcsv($csvFile, ["Day: " . $day], ';');
                fputcsv($csvFile, ["Slot", "Raum", "Bewerber", "StudiengangId", "Erstinterviewer", "StudiengangId", "Zweitinterviewer", "StudiengangId", "Protokollant", "StudiengangId"], ';');
            }
            Log::info("counterSlots: " . $counterSlots);
            $counterRooms =0;
            foreach($resultingArraySingleSlot as $n => $resultingArraySingleSlotAndRoom){// ($n = 0; $n < count($resultingArray[$i]); $n++) {
                fputcsv($csvFile, [$counterSlots, $counterRooms, $resultingArray[$i][$n]->applicant->getFirstName() . "_" . $resultingArray[$i][$n]->applicant->getLastName(), $resultingArray[$i][$n]->applicant->getStudyDirectionID(),
                    $resultingArray[$i][$n]->interviewer1->getFirstName() . "_" . $resultingArray[$i][$n]->interviewer1->getLastName(), $resultingArray[$i][$n]->interviewer1->getStudyDirectionID(),
                    $resultingArray[$i][$n]->interviewer2->getFirstName() . "_" . $resultingArray[$i][$n]->interviewer2->getLastName(), $resultingArray[$i][$n]->interviewer2->getStudyDirectionID(),
                    $resultingArray[$i][$n]->transcriptWriter->getFirstName() . "_" . $resultingArray[$i][$n]->transcriptWriter->getLastName(), $resultingArray[$i][$n]->transcriptWriter->getStudyDirectionID()], ';');
                $counterRooms++;
            }
            $counterSlots++;
        }

        fputcsv($csvFile, [""], ';');
        fputcsv($csvFile, ["Applicants that do not have a Interview:"], ';');
        for ($o = 0; $o < count($applicantsArr); $o++) {
            if (!$applicantsArr[$o]->getHasInterview()) {
                fputcsv($csvFile, [$applicantsArr[$o]->getFirstName() . "_" . $applicantsArr[$o]->getLastName()]);
            }
        }

        fputcsv($csvFile, [""], ';');
        fputcsv($csvFile, ["Information about Interviewers:"], ';');
        fputcsv($csvFile, ["Name", "anzInterviewsTotal", "anzErstInterviews", "anzZweitInterviews", "anzProtokoll", "Erfahrungslevel"], ';');;
        for ($o = 0; $o < count($interviewersArr); $o++) {
            fputcsv($csvFile, [$interviewersArr[$o]->getFirstName() . "_" . $interviewersArr[$o]->getLastName(), $interviewersArr[$o]->getNumInterviewsTotal(), $interviewersArr[$o]->getNum1Interviews(), $interviewersArr[$o]->getNum2Interviews(), $interviewersArr[$o]->getNumTranscripts(), $interviewersArr[$o]->getExperienceLevel()], ';');
        }

        fputcsv($csvFile, [""], ';');
        fputcsv($csvFile, ["Information about amount of Interviews done together By Interviewer:"], ';');
        $headerRow = [""];
        foreach ($interviewersArr as $interviewer) {
            $headerRow[] = $interviewer->getFirstName() . "_" . $interviewer->getLastName();
        }
        fputcsv($csvFile, $headerRow, ';');

        // Populate the rest of the matrix
        foreach ($interviewersArr as $interviewer1) {
            $row = [];
            $row[] = $interviewer1->getFirstName() . "_" . $interviewer1->getLastName();
            foreach ($interviewersArr as $interviewer2) {
                $value = $interviewer1->getValueFromInterviewerMap($interviewer2->getFirstName() . "_" . $interviewer2->getLastName());
                $row[] = $value;
            }
            fputcsv($csvFile, $row, ';');
        }



        return response()->download($csvPath,'RotationPlan.csv', [
            'Content-Type' => 'text/csv',
        ]);


    }

    function fillResultingArrayGreedy(array $allInterviewCombinations, array $interviewersArr, int $anzSlotsFreitag , int $anzSlotsSamstag ,int $anzSlotsSonntag, int $anzRaeume) : array{
        /**
         * @var Interview[][] $resultingArray
         */
        $resultingArray = [];
        $day = 0;
        $numInterviewer = count($interviewersArr);
        for ($i = 0; $i < ($anzSlotsFreitag + $anzSlotsSamstag + $anzSlotsSonntag); $i++) {
            if ($i == $anzSlotsFreitag)
                $day = 1;
            if ($i == $anzSlotsSamstag+$anzSlotsSamstag)
                $day = 2;

            /* TODO wird nicht gesetzt?!
             [isTechie:App\Http\Controllers\Interviewer:private] =>
                    [isFemale:App\Http\Controllers\Interviewer:private] =>
            */

            Log::info("Slot: " . $i);
            /**
             * @var Interview[] $bestInterviewCombinations
             */
            $bestInterviewCombinations = [];
            $lowestHighestPrioNum = PHP_INT_MIN; //the prio num that is the minimum one of the prio nums of the bestInterviewCombinations
            foreach ($allInterviewCombinations as $n => $interviewCombination) {
                $interviewCombination->calculatePrioNum($day, $i, $anzSlotsFreitag, $anzSlotsSamstag, $anzSlotsSonntag, $anzRaeume, $numInterviewer);
                //Log::info("currentCombination First interviewer and applicant: " . $interviewCombination->interviewer1->getFirstName() . "_" . $interviewCombination->applicant->getFirstName());

                if ($interviewCombination->prioNumber > $lowestHighestPrioNum) {
                    $arr = $this->removeCombinationsWithSameParticipantsIfNecessary($interviewCombination, $bestInterviewCombinations);
                    if(!$arr[1])
                        continue;

                    $bestInterviewCombinations = $arr[0];

                    $bestInterviewCombinations[] = $interviewCombination;
                    Log::info("Slot: " . $i . " " . " bestCombinations " . count($bestInterviewCombinations));
                    if (count($bestInterviewCombinations) > $anzRaeume) {
                        unset($bestInterviewCombinations[$this->calculateIndexOfLowestPrioNum($bestInterviewCombinations)]);
                    }
                    Log::info("Slot: " . $i . " " . " bestCombinations AfterRemoval" . count($bestInterviewCombinations));
                    $lowestHighestPrioNum = $this->recalculateLowestPrioNum($bestInterviewCombinations, $anzRaeume);
                }
            }

            //TODO änderung des Codes hin zu jeden Raum einzelnd ODER alle RaumCombis durchgehen?
            //TODO ausrechnen wie viele Slots wir unbedingt brauchen, damit Applicants noch einen Slot bekommen

            $lowestHighestPrioNum = PHP_INT_MIN;
            //if the bestCombis array isnt filled completly -> loop over all combinations and just choose the ones that do not kick anything
            if (count($bestInterviewCombinations) < $anzRaeume) {
                foreach ($allInterviewCombinations as $n => $interviewCombination) {
                    Log::info("Slot: " . $i . " " . " bestCombinations 2nd Loop " . count($bestInterviewCombinations) . " lowestHighestPrioNum: " . $lowestHighestPrioNum);
                    if ($interviewCombination->prioNumber > $lowestHighestPrioNum) {
                        if ($this->checkIfAnotherInterviewHasSameParticipants($interviewCombination, $bestInterviewCombinations)) {
                            Log::info("continue");
                            continue;
                        }
                        Log::info("added interviewerCombination");
                        $bestInterviewCombinations[] = $interviewCombination;
                        if (count($bestInterviewCombinations) > $anzRaeume) {
                            unset($bestInterviewCombinations[$this->calculateIndexOfLowestPrioNum($bestInterviewCombinations)]);
                        }
                        $lowestHighestPrioNum = $this->recalculateLowestPrioNum($bestInterviewCombinations, $anzRaeume);
                        Log::info("Slot: " . $i . " " . " bestCombinations 2nd Loop End" . count($bestInterviewCombinations) . "lowestHighestPrioNum: " . $lowestHighestPrioNum);
                    }
                }
            }

            Log::info("bestInterviewCombinations: " . count($bestInterviewCombinations));
            //The interviews get entered in the array and the values of the participants get adjusted accordingly
            foreach ($bestInterviewCombinations as $m => $combination) {
                $resultingArray[$i][$m] = $combination;
                Log::info("put Into:  " . $i. " " . $m);
                $combination->updateValuesAfterInterviewSpotSecured($i);

                foreach ($allInterviewCombinations as $key => $interview) {
                    if ($interview->applicant === $combination->applicant) {
                        unset($allInterviewCombinations[$key]);
                    }
                }
                Log::info("deleted Everything for: " . $combination->applicant);
                Log::info("Count AllInterviewCombinations after unsetting: " . count($allInterviewCombinations));
            }

        }
        return $resultingArray;
    }


    function removeCombinationsWithSameParticipantsIfNecessary(Interview $curCombination, &$bestInterviewCombinations): array
    {

        /**
         * @var Interview[] $newBestInterviewCombinations
         */
        $newBestInterviewCombinations = [];
        $isHigherThanCombinationsThatWouldHaveToBeRemoved = true;

        //Log:info("Best InterviewCombinations: " . json_encode($bestInterviewCombinations));
        foreach ($bestInterviewCombinations as $bestCombination) {
            if ($curCombination->hasSameParticipants($bestCombination)) {
                $isHigherThanCombinationsThatWouldHaveToBeRemoved = $isHigherThanCombinationsThatWouldHaveToBeRemoved && $bestCombination->prioNumber < $curCombination->prioNumber;
            } else {
                $newBestInterviewCombinations[] = $bestCombination;
            }

        }
        if (!$isHigherThanCombinationsThatWouldHaveToBeRemoved) {
            return [$bestInterviewCombinations, false];
        }

        return [$newBestInterviewCombinations, true];
    }

    private
    function calculateIndexOfLowestPrioNum(&$bestInterviewCombinations): int
    {
        $lowestPrioNum = null;
        $indexToRemove = null;

        foreach ($bestInterviewCombinations as $index => $combination) {
            if ($lowestPrioNum === null || $combination->prioNumber < $lowestPrioNum) {
                $indexToRemove = $index;
                $lowestPrioNum = $combination->prioNumber;
            }
        }
        return $indexToRemove;
    }

    private function recalculateLowestPrioNum(array $bestInterviewCombinations, int $anzRaeume): int
    {

        $lowestPrioNum = PHP_INT_MAX;
        foreach ($bestInterviewCombinations as $combination) {
            if ($combination->prioNumber < $lowestPrioNum) {
                $lowestPrioNum = $combination->prioNumber;
            }
        }
        return count($bestInterviewCombinations) < $anzRaeume ? PHP_INT_MIN : $lowestPrioNum;
    }

    private
    function checkIfAnotherInterviewHasSameParticipants(Interview $curCombination, array $bestInterviewCombinations)
    {
        foreach ($bestInterviewCombinations as $combination) {
            if ($curCombination->hasSameParticipants($combination)) {
                return true;
            }
        }
        return false;

    }

    private
    function readCSV($filename)
    {
        $filePath = storage_path('app/public/' . $filename);

        // Check if the file exists
        if (!Storage::exists('public/' . $filename)) {
            abort(404, 'CSV file not found');
        }

        // Read the CSV file with semicolon as delimiter
        $csvData = array_map(function ($row) {
            return str_getcsv($row, ';');
        }, file($filePath));

        // Remove the header row
        $header = array_shift($csvData);

        // Prepare the data array
        $data = [];

        // Map each row to an associative array using the header as keys
        foreach ($csvData as $row) {
            $data[] = array_combine($header, $row);
        }

        return $data;
    }
}


class SliderValues
{
    //"+ mind. 1 weibliche Person",
    //"+ mind. 1 männliche Person",
    //"+ Techie bei Techie",
    //"- mehr als 2 Techies",
    //"+ Bewerber gleiches Studium",
    //"- Dopplung des Studiengangs",
    //"- Tripplung des Studiengangs",
    //"- schon zusammen Interviewed",
    //"+ Prio Semi/Unerfahren",
    //"+/- Protokoll richtige anz.",
    //"+/- 2. Interview richtige anz.",
    //"+/- 1. Interview richtige anz.",
    //"+ Interviewer verkettete Slots",
    //"- erstes Interview am Tag",
    //"+/- gleich viele Interviews",
    //"+ weniger Tage anwesend",
    public static int $atLeast1Female_pos;
    public static int $atLeast1Male_pos;
    public static int $techieWithTechie_pos;
    public static int $TwoTechies_neg;
    public static int $applicantsSameStudyDirection_pos;
    public static int $duplicationOfStudyDirection_neg;
    public static int $triplicationOfStudyDirection_neg;
    public static int $alreadyInterviewedTogether_neg;
    public static int $priorityForSemiOrUnexperienced_pos;
    public static int $correctNumberOfTranscripts_both;
    public static int $correctNumberOfSecondInterviews_both;
    public static int $correctNumberOfFirstInterviews_both;
    public static int $interviewerConsecutiveSlots_both;
    public static int $firstInterviewOfTheDay_neg;
    public static int $equalNumberOfInterviews_both;
    public static int $fewerDaysPresent_pos;

    public static function initialize(array $sliderValues): void
    {
        self::$atLeast1Female_pos = $sliderValues[0];
        self::$atLeast1Male_pos = $sliderValues[1];
        self::$techieWithTechie_pos = $sliderValues[2];
        self::$TwoTechies_neg = $sliderValues[3];
        self::$applicantsSameStudyDirection_pos = $sliderValues[4];
        self::$duplicationOfStudyDirection_neg = $sliderValues[5];
        self::$triplicationOfStudyDirection_neg = $sliderValues[6];
        self::$alreadyInterviewedTogether_neg = $sliderValues[7];
        self::$priorityForSemiOrUnexperienced_pos = $sliderValues[8];
        self::$correctNumberOfTranscripts_both = $sliderValues[9];
        self::$correctNumberOfSecondInterviews_both = $sliderValues[10];
        self::$correctNumberOfFirstInterviews_both = $sliderValues[11];
        self::$interviewerConsecutiveSlots_both = $sliderValues[12];
        self::$firstInterviewOfTheDay_neg = $sliderValues[13];
        self::$equalNumberOfInterviews_both = $sliderValues[14];
        self::$fewerDaysPresent_pos = $sliderValues[15];
    }
}


class Interviewer
{
    private string $firstName;
    private string $lastName;
    private bool $isTechie;
    private bool $isFemale;
    private bool $fridayAvailability;
    private bool $saturdayAvailability;
    private bool $sundayAvailability;
    private int $studyDirectionID;
    private ExperienceLevel $experienceLevel;
    private array $timeSlotsWithInterviews;
    private int $numInterviewsTotal;
    private int $numTranscripts;
    private int $num1Interviews;
    private int $num2Interviews;
    private int $totalAmountSlots;
    private ExperienceLevel $currentExperienceLevel;
    private array $interviewerMap;

    public function __toString()
    {
        return "Interviewer: " . $this->getFirstName() . " " . $this->getLastName();
    }

    public function __construct(array $data, int $totalAmountOfSlots)
    {

        // Extract data from the array
        $this->firstName = $data['Vorname'];
        $this->lastName = $data['Nachname'];
        $this->isTechie = (bool)$data['IstTechie'];
        $this->isFemale = (bool)$data['IstWeiblich'];
        $this->fridayAvailability = (bool)$data['Freitag'];
        $this->saturdayAvailability = (bool)$data['Samstag'];
        $this->sundayAvailability = (bool)$data['Sonntag'];
        $this->studyDirectionID = (int)$data['StudiengangsrichtungID'];
        $this->experienceLevel = ExperienceLevel::fromNumber($data['ErfahrungsGrad']);
        $this->totalAmountSlots = $totalAmountOfSlots;
        $this->timeSlotsWithInterviews = array_fill(0, $totalAmountOfSlots, false);
        $this->numInterviewsTotal = 0;
        $this->numTranscripts = 0;
        $this->num1Interviews = 0;
        $this->num2Interviews = 0;
        $this->currentExperienceLevel = ExperienceLevel::fromNumber($data['ErfahrungsGrad']);
        $this->interviewerMap = [];
    }


    public function updateValuesGeneral(int $indexSlot, bool $roleIsTranscript, bool $roleIs2Interview, bool $roleIs1Interview)
    {
        $this->timeSlotsWithInterviews[$indexSlot] = true;
        $this->numInterviewsTotal += 1;

        if ($roleIsTranscript)
            $this->numTranscripts += 1;
        else if ($roleIs2Interview)
            $this->num2Interviews += 1;
        else if ($roleIs1Interview)
            $this->num1Interviews += 1;

        if ($this->getCurrentExperienceLevel() == ExperienceLevel::SEMIEXPERIENCED and $this->num2Interviews >= 2) {
            $this->currentExperienceLevel = ExperienceLevel::fromNumber(3);
        } else if ($this->getCurrentExperienceLevel() == ExperienceLevel::UNEXPERIENCED and $this->numTranscripts >= 3) {
            $this->currentExperienceLevel = ExperienceLevel::fromNumber(2);
        }
    }

    public function hadInterviewOneSlotBefore(int $slot, int $numSlotsFriday, int $numSlotsSaturday, int $day): bool
    {
        if ($slot == 0)
            return false;

        if (!$this->timeSlotsWithInterviews[$slot - 1])
            return false;

        //check if the slot before was the day before
        //3 Slots on Friday, 4 Saturday -> 3 first slot saturday, 7 first one sunday
        if ($slot == $numSlotsFriday or $slot == $numSlotsSaturday + $numSlotsFriday)
            return false;

        return true;
    }

    public function hadInterviewThisDay(int $slot, int $numSlotsFriday, int $numSlotsSaturday, int $day): bool
    {
        if ($slot == 0)
            return false;

        //check if the slot is the first of the day
        if ($slot == $numSlotsFriday or $slot == $numSlotsSaturday + $numSlotsFriday)
            return false;

        $startSlot = 0;
        $endSlot = $numSlotsFriday;
        if ($day == 1) {
            $startSlot = $numSlotsFriday;
            $endSlot = $numSlotsSaturday + $numSlotsFriday;
        } elseif ($day == 2) {
            $startSlot = $numSlotsSaturday + $numSlotsFriday;
            $endSlot = $this->totalAmountSlots - 1;
        }

        for ($i = $startSlot; $i <= $endSlot; $i++) {

            if ($this->timeSlotsWithInterviews[$i])
                return true;

        }
        return false;
    }

    public function incrementInterviewerMap(string $firstName_LastName): void
    {
        if (array_key_exists($firstName_LastName, $this->interviewerMap)) {
            $this->interviewerMap[$firstName_LastName]++;
        } else {
            $this->interviewerMap[$firstName_LastName] = 1;
        }
    }

    public function getValueFromInterviewerMap(string $firstName_LastName): int
    {
        return isset($this->interviewerMap[$firstName_LastName]) ? $this->interviewerMap[$firstName_LastName] : 0;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getIsTechie(): bool
    {
        return $this->isTechie;
    }

    public function getIsFemale(): bool
    {
        return $this->isFemale;
    }

    public function getFridayAvailability(): bool
    {
        return $this->fridayAvailability;
    }

    public function getSaturdayAvailability(): bool
    {
        return $this->saturdayAvailability;
    }

    public function getSundayAvailability(): bool
    {
        return $this->sundayAvailability;
    }

    public function getStudyDirectionID(): int
    {
        return $this->studyDirectionID;
    }

    public function getExperienceLevel(): int
    {
        return $this->experienceLevel->getLevel();
    }

    public function getTimeSlotsWithInterviews(): array
    {
        return $this->timeSlotsWithInterviews;
    }

    public function getNumInterviewsTotal(): int
    {
        return $this->numInterviewsTotal;
    }

    public function getNumTranscripts(): int
    {
        return $this->numTranscripts;
    }

    public function getNum1Interviews(): int
    {
        return $this->num1Interviews;
    }

    public function getNum2Interviews(): int
    {
        return $this->num2Interviews;
    }

    public function getCurrentExperienceLevel(): int
    {
        return $this->currentExperienceLevel->getLevel();
    }

    public function getOptimalNumberTranscripts(): int
    {
        //ursprünglichErfahrenen ~2 Protokollant ~4 erstinterviews ~3 zweit
        //ursprünglichSemierfahren: 3 Protokoll, 3 erst, 3 zweit
        //ursprünglichUnerfahren: 4 Protokoll, 2 erst, 3 zweit
        //TODO this could be put into a slider as well
        switch ($this->getExperienceLevel()) {
            case ExperienceLevel::UNEXPERIENCED:
                return 4;
            case ExperienceLevel::SEMIEXPERIENCED:
                return 3;
            case ExperienceLevel::EXPERIENCED:
                return 2;
            default:
                throw new InvalidArgumentException("SwitchCase has every level tested");
        }
    }

    public function getOptimalNumber2Interviews(): int
    {
        //TODO this could be put into a slider as well
        return 3;
        //ursprünglichErfahrenen ~2 Protokollant ~4 erstinterviews ~3 zweit
        //ursprünglichSemierfahren: 3 Protokoll, 3 erst, 3 zweit
        //ursprünglichUnerfahren: 4 Protokoll, 2 erst, 3 zweit
    }

    public function getOptimalNumber1Interviews(): int
    {
        //TODO this could be put into a slider as well
        //ursprünglichErfahrenen ~2 Protokollant ~4 erstinterviews ~3 zweit
        //ursprünglichSemierfahren: 3 Protokoll, 3 erst, 3 zweit
        //ursprünglichUnerfahren: 4 Protokoll, 2 erst, 3 zweit
        switch ($this->getExperienceLevel()) {
            case ExperienceLevel::UNEXPERIENCED:
                return 2;
            case ExperienceLevel::SEMIEXPERIENCED:
                return 3;
            case ExperienceLevel::EXPERIENCED:
                return 4;
            default:
                throw new InvalidArgumentException("SwitchCase has every level tested");
        }
    }


}

class Applicant
{
    private string $firstName;
    private string $lastName;
    private bool $isTechie;
    private bool $isFemale;
    private bool $fridayAvailability;
    private bool $saturdayAvailability;
    private bool $sundayAvailability;
    private int $studyDirectionID;
    private bool $isRepeatApplicant;

    private bool $hasInterview;

    public function __toString()
    {
        return "Applicant: " . $this->getFirstName() . " " . $this->getLastName();
    }

    public function __construct(array $data)
    {
        // Extract data from the array
        $this->firstName = $data['Vorname'];
        $this->lastName = $data['Nachname'];
        $this->isTechie = (bool)$data['IstTechie'];
        $this->isFemale = (bool)$data['IstWeiblich'];
        $this->fridayAvailability = (bool)$data['Freitag'];
        $this->saturdayAvailability = (bool)$data['Samstag'];
        $this->sundayAvailability = (bool)$data['Sonntag'];
        $this->studyDirectionID = (int)$data['StudiengangsrichtungID'];
        $this->isRepeatApplicant = (bool)$data['Wiederbewerber'];
        $this->hasInterview = false;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getIsTechie(): bool
    {
        return $this->isTechie;
    }

    public function getIsFemale(): bool
    {
        return $this->isFemale;
    }

    public function getFridayAvailability(): bool
    {
        return $this->fridayAvailability;
    }

    public function getSaturdayAvailability(): bool
    {
        return $this->saturdayAvailability;
    }

    public function getSundayAvailability(): bool
    {
        return $this->sundayAvailability;
    }

    public function getStudyDirectionID(): int
    {
        return $this->studyDirectionID;
    }

    public function getIsRepeatApplicant(): bool
    {
        return $this->isRepeatApplicant;
    }

    public function getHasInterview(): bool
    {
        return $this->hasInterview;
    }

    public function setHasInterview(bool $hasInterview): void
    {
        $this->hasInterview = $hasInterview;
    }

    public function howManySlotsHasApplicantLeft(int $day, int $slot, int $numSlotsFriday, int $numSlotsSaturday, int $numSlotsSunday) : int
    {
        if ($day == 0)
        {
            $numSlots = 0;
            if($this->fridayAvailability)
                $numSlots = $numSlotsFriday- $slot;
            if($this->saturdayAvailability)
                $numSlots += $numSlotsSaturday;
            if ($this->sundayAvailability)
                $numSlots += $numSlotsSunday;
            return $numSlots;
        }
        if($day ==1)
        {
            $numSlots = 0;
            if($this->saturdayAvailability)
                $numSlots = $numSlotsSaturday + $numSlotsFriday - $slot;
            if ($this->sundayAvailability)
                $numSlots += $numSlotsSunday;
            return $numSlots;
        }
        elseif($day ==2)
        {
            return $numSlotsSaturday + $numSlotsFriday+$numSlotsSunday-$slot;
        }
        else
            throw new InvalidArgumentException("Illegal Day");


    }
}


class Interview
{
    public Applicant $applicant;
    public Interviewer $interviewer1;
    public Interviewer $interviewer2;
    public Interviewer $transcriptWriter;

    public bool $unallowedPosition;

    public int $prioNumber;

    public int $prioNumberStatic;

    public function __toString()
    {
        return "Applicant: " . $this->applicant->getFirstName() . " " . $this->applicant->getLastName() .
            ", Interviewer 1: " . $this->interviewer1->getFirstName() . " " . $this->interviewer1->getLastName() .
            ", Interviewer 2: " . $this->interviewer2->getFirstName() . " " . $this->interviewer2->getLastName() .
            ", Transcript Writer: " . $this->transcriptWriter->getFirstName() . " " . $this->transcriptWriter->getLastName() .
            ", Priority Number: " . $this->prioNumber;
    }

    public function __construct(Applicant $applicant, Interviewer $Interviewer1, Interviewer $Interviewer2, Interviewer $TranscriptWriter)
    {
        $this->applicant = $applicant;
        $this->interviewer1 = $Interviewer1;
        $this->interviewer2 = $Interviewer2;
        $this->transcriptWriter = $TranscriptWriter;
        $this->prioNumber = 0;
        $this->unallowedPosition = false;
        $this->prioNumberStatic = $this->calculatePrioNumThatDoesntChange();
    }


    public function updateValuesAfterInterviewSpotSecured($indexSlot)
    {
        $this->applicant->setHasInterview(true);
        $this->interviewer1->updateValuesGeneral($indexSlot, false, false, true);
        $this->interviewer2->updateValuesGeneral($indexSlot, false, true, false);
        $this->transcriptWriter->updateValuesGeneral($indexSlot, true, false, false);

        $this->interviewer1->incrementInterviewerMap($this->interviewer2->getFirstName() . "_" . $this->interviewer2->getLastName());
        $this->interviewer1->incrementInterviewerMap($this->transcriptWriter->getFirstName() . "_" . $this->transcriptWriter->getLastName());
        $this->interviewer2->incrementInterviewerMap($this->interviewer1->getFirstName() . "_" . $this->interviewer1->getLastName());
        $this->interviewer2->incrementInterviewerMap($this->transcriptWriter->getFirstName() . "_" . $this->transcriptWriter->getLastName());
        $this->transcriptWriter->incrementInterviewerMap($this->interviewer1->getFirstName() . "_" . $this->interviewer1->getLastName());
        $this->transcriptWriter->incrementInterviewerMap($this->interviewer2->getFirstName() . "_" . $this->interviewer2->getLastName());
    }

    public function calculatePrioNum(int $day, int $slot, int $numSlotsFriday, int $numSlotsSaturday, int $numSlotsSunday, int $numRooms, int $totalNumInterviewers)
    {
        if ($this->applicant->getHasInterview() or !$this->isEveryoneAvailable($day)) {
            $this->prioNumber = PHP_INT_MIN;
            return;
        }
        $this->prioNumber = $this->deductionsNotAllowedPositions();
        $this->prioNumber += $this->prioNumberStatic;

        $this->prioNumber -= SliderValues::$alreadyInterviewedTogether_neg * ($this->interviewer1->getValueFromInterviewerMap(($this->interviewer2->getFirstName() . "_" . $this->interviewer2->getLastName())) + $this->interviewer1->getValueFromInterviewerMap(($this->transcriptWriter->getFirstName() . "_" . $this->transcriptWriter->getLastName())) + $this->interviewer2->getValueFromInterviewerMap(($this->transcriptWriter->getFirstName() . "_" . $this->transcriptWriter->getLastName())));
        if ($this->transcriptWriter->getCurrentExperienceLevel() == ExperienceLevel::UNEXPERIENCED) {
            $this->prioNumber += SliderValues::$priorityForSemiOrUnexperienced_pos;
        }
        if ($this->interviewer2->getCurrentExperienceLevel() == ExperienceLevel::SEMIEXPERIENCED) {
            $this->prioNumber += SliderValues::$priorityForSemiOrUnexperienced_pos;
        }
        $this->prioNumber += SliderValues::$correctNumberOfTranscripts_both *  ($this->transcriptWriter->getOptimalNumberTranscripts() - $this->transcriptWriter->getNumTranscripts());
        $this->prioNumber += SliderValues::$correctNumberOfSecondInterviews_both *  ($this->interviewer2->getOptimalNumber2Interviews() - $this->interviewer2->getNum2Interviews());
        $this->prioNumber += SliderValues::$correctNumberOfFirstInterviews_both *  ($this->interviewer1->getOptimalNumber1Interviews() - $this->interviewer1->getNum1Interviews());
        $this->prioNumber += SliderValues::$interviewerConsecutiveSlots_both * ($this->interviewer1->hadInterviewOneSlotBefore($slot, $numSlotsFriday, $numSlotsSaturday, $day) + $this->interviewer2->hadInterviewOneSlotBefore($slot, $numSlotsFriday, $numSlotsSaturday, $day) + $this->transcriptWriter->hadInterviewOneSlotBefore($slot, $numSlotsFriday, $numSlotsSaturday, $day));
        //if the person has already had an interview this day, but not the last one, then the person should not have to come back in again
        $this->prioNumber -= SliderValues::$interviewerConsecutiveSlots_both * (($this->interviewer1->hadInterviewOneSlotBefore($slot, $numSlotsFriday, $numSlotsSaturday, $day) == false and $this->interviewer1->hadInterviewThisDay($slot, $numSlotsFriday, $numSlotsSaturday, $day) == true) + ($this->interviewer2->hadInterviewOneSlotBefore($slot, $numSlotsFriday, $numSlotsSaturday, $day) == false and $this->interviewer2->hadInterviewThisDay($slot, $numSlotsFriday, $numSlotsSaturday, $day) == true) + ($this->transcriptWriter->hadInterviewOneSlotBefore($slot, $numSlotsFriday, $numSlotsSaturday, $day) == false and $this->transcriptWriter->hadInterviewThisDay($slot, $numSlotsFriday, $numSlotsSaturday, $day) == true));
        $this->prioNumber -= SliderValues::$firstInterviewOfTheDay_neg * (!$this->interviewer1->hadInterviewThisDay($slot, $numSlotsFriday, $numSlotsSaturday, $day) + !$this->interviewer2->hadInterviewThisDay($slot, $numSlotsFriday, $numSlotsSaturday, $day) + !$this->transcriptWriter->hadInterviewThisDay($slot, $numSlotsFriday, $numSlotsSaturday, $day));

        $avgNumInterviews = (int)(($numSlotsFriday + $numSlotsSaturday + $numSlotsSunday) * $numRooms / $totalNumInterviewers);
        $this->prioNumber += SliderValues::$equalNumberOfInterviews_both *  ($avgNumInterviews - $this->interviewer1->getNumInterviewsTotal());
        $this->prioNumber += SliderValues::$equalNumberOfInterviews_both *  ($avgNumInterviews - $this->interviewer2->getNumInterviewsTotal());
        $this->prioNumber += SliderValues::$equalNumberOfInterviews_both *   ($avgNumInterviews - $this->transcriptWriter->getNumInterviewsTotal());
        $this->prioNumber += SliderValues::$fewerDaysPresent_pos * $this->howManyDaysIsEveryoneAway($day);
        $this->prioNumber += SliderValues::$fewerDaysPresent_pos * ($numSlotsFriday+$numSlotsSaturday+$numSlotsSunday - $this->applicant->howManySlotsHasApplicantLeft($day,$slot,$numSlotsFriday,$numSlotsSaturday,$numSlotsSunday));

    }

    private function calculatePrioNumThatDoesntChange() :int
    {
        $prioNumberStatic = 0;

        if ($this->interviewer1->getIsFemale() or $this->interviewer2->getIsFemale() or $this->transcriptWriter->getIsFemale()) {
            $prioNumberStatic += SliderValues::$atLeast1Female_pos;
        }
        if (!$this->interviewer1->getIsFemale() or !$this->interviewer2->getIsFemale() or !$this->transcriptWriter->getIsFemale()) {
            $prioNumberStatic += SliderValues::$atLeast1Male_pos;
        }
        if ($this->applicant->getIsTechie() and ($this->interviewer1->getIsTechie() or $this->interviewer2->getIsTechie())) {
            $prioNumberStatic += SliderValues::$techieWithTechie_pos;
        }
        if (($this->interviewer1->getIsTechie() and $this->interviewer2->getIsTechie())) {
            $prioNumberStatic -= SliderValues::$TwoTechies_neg;
        }
        if ($this->applicant->getStudyDirectionID() == $this->interviewer1->getStudyDirectionID() or $this->applicant->getStudyDirectionID() == $this->interviewer2->getStudyDirectionID() or $this->applicant->getStudyDirectionID() == $this->transcriptWriter->getStudyDirectionID()) {
            $prioNumberStatic += SliderValues::$applicantsSameStudyDirection_pos;
        }
        if ($this->interviewer1->getStudyDirectionID() == $this->interviewer2->getStudyDirectionID() or $this->interviewer1->getStudyDirectionID() == $this->transcriptWriter->getStudyDirectionID() or $this->transcriptWriter->getStudyDirectionID() == $this->interviewer2->getStudyDirectionID()) {
            $prioNumberStatic -= SliderValues::$duplicationOfStudyDirection_neg;
        }
        if ($this->interviewer1->getStudyDirectionID() == $this->interviewer2->getStudyDirectionID() and $this->interviewer1->getStudyDirectionID() == $this->transcriptWriter->getStudyDirectionID()) {
            $prioNumberStatic -= SliderValues::$triplicationOfStudyDirection_neg;
        }
        return $prioNumberStatic;
    }

    private function isEveryoneAvailable(int $day): bool
    {
        if ($day == 0) {
            return $this->applicant->getFridayAvailability() and $this->interviewer1->getFridayAvailability() and $this->interviewer2->getFridayAvailability() and $this->transcriptWriter->getFridayAvailability();
        }
        if ($day == 1) {
            return $this->applicant->getSaturdayAvailability() and $this->interviewer1->getSaturdayAvailability() and $this->interviewer2->getSaturdayAvailability() and $this->transcriptWriter->getSaturdayAvailability();
        }
        if ($day == 2) {
            return $this->applicant->getSundayAvailability() and $this->interviewer1->getSundayAvailability() and $this->interviewer2->getSundayAvailability() and $this->transcriptWriter->getSundayAvailability();
        }
        throw new InvalidArgumentException("This day doesnt exist");
    }

    private function howManyDaysIsEveryoneAway(int $day): int
    {
        $awayCount = 0;
        if ($day == 2) {
            return 0;
        }
        if ($day == 0) {
            if (!$this->applicant->getSaturdayAvailability()) {
                $awayCount++;
            }
            if (!$this->interviewer1->getSaturdayAvailability()) {
                $awayCount++;
            }
            if (!$this->interviewer2->getSaturdayAvailability()) {
                $awayCount++;
            }
            if (!$this->transcriptWriter->getSaturdayAvailability()) {
                $awayCount++;
            }
        }
        if (!$this->applicant->getSundayAvailability()) {
            $awayCount++;
        }
        if (!$this->interviewer1->getSundayAvailability()) {
            $awayCount++;
        }
        if (!$this->interviewer2->getSundayAvailability()) {
            $awayCount++;
        }
        if (!$this->transcriptWriter->getSundayAvailability()) {
            $awayCount++;
        }
        return $awayCount;
    }

    private function deductionsNotAllowedPositions(): int
    {
        $result = 0;
        if($this->interviewer1->getCurrentExperienceLevel() == ExperienceLevel::UNEXPERIENCED)
        {
            $result -= 10000000;
            $this->unallowedPosition = true;
        }
        if($this->interviewer1->getCurrentExperienceLevel() == ExperienceLevel::SEMIEXPERIENCED){
            $result -= 1000000;
            $this->unallowedPosition = true;
        }
        if($this->interviewer2->getCurrentExperienceLevel() == ExperienceLevel::UNEXPERIENCED){
            $result -= 1000000;
            $this->unallowedPosition = true;
        }

        return $result;
    }

    public function hasSameParticipants(Interview $otherInterview): bool
    {
        if ($this->applicant === $otherInterview->applicant) {
            Log::info("applicant: the same");
            return true;
        }
        if ($this->interviewer1 === $otherInterview->interviewer1 ||
            $this->interviewer1 === $otherInterview->interviewer2 ||
            $this->interviewer1 === $otherInterview->transcriptWriter ||
            $this->interviewer2 === $otherInterview->interviewer1 ||
            $this->interviewer2 === $otherInterview->interviewer2 ||
            $this->interviewer2 === $otherInterview->transcriptWriter ||
            $this->transcriptWriter === $otherInterview->interviewer1 ||
            $this->transcriptWriter === $otherInterview->interviewer2 ||
            $this->transcriptWriter === $otherInterview->transcriptWriter) {

            return true;
        }
        return false;
    }

}


class ExperienceLevel
{
    const UNEXPERIENCED = 1;
    const SEMIEXPERIENCED = 2;
    const EXPERIENCED = 3;

    private $level;

    public function __construct(int $level)
    {
        if (!in_array($level, [self::UNEXPERIENCED, self::SEMIEXPERIENCED, self::EXPERIENCED])) {
            throw new InvalidArgumentException("Invalid experience level: $level");
        }
        $this->level = $level;
    }

    /**
     * @return int
     */
    public function getLevel(): int
    {
        return $this->level;
    }

    public static function fromNumber(int $number): self
    {
        switch ($number) {
            case self::UNEXPERIENCED:
                return new self(self::UNEXPERIENCED);
            case self::SEMIEXPERIENCED:
                return new self(self::SEMIEXPERIENCED);
            case self::EXPERIENCED:
                return new self(self::EXPERIENCED);
            default:
                throw new InvalidArgumentException("Invalid experience level number: $number");

        }
    }
}

