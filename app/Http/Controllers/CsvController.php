<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
//use Illuminate\Http\File;
use phpDocumentor\Reflection\Types\Null_;
use PhpOption\None;


//use Spatie\FlareClient\Http\Response;
//use Illuminate\Auth\Access\Response;
//use Illuminate\Http\Response;
//use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\File;
//use Response;
class CsvController extends Controller
{

    public function get_csv()
    {

        // these are the headers for the csv file.
        $headers = array(
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-Disposition' => 'attachment; filename=download.csv',
            'Expires' => '0',
            'Pragma' => 'public',
        );


        //I am storing the csv file in public >> files folder. So that why I am creating files folder
        if (!File::exists(public_path() . "/files")) {
            File::makeDirectory(public_path() . "/files");
        }

        //creating the download file
        $filename =  public_path("files/download.csv");
        $handle = fopen($filename, 'w');

        //adding the first row
        fputcsv($handle, ['SlotNum','RoomNum','Interviewee', 'ErstInterviewer*in', 'ZweitInterviewer*in', 'Protokollant*in', 'PriorityNumber',]);

        //adding the data from the array
        $rot = new Rotationsplan();
        $collection = $rot->testRotationsPlanErstellen();

        $slotCounter = 0;

        foreach ($collection as $day) {

            fputcsv($handle, array("____New Day____"));

            foreach ($day as $slot) {

                fputcsv($handle, array( ''));
                fputcsv($handle, array("_New Slot_"));
                foreach ($slot as $el) {
                    /** @var Zeitslot $curZeitSlot */
                    $curZeitSlot = $el;
                    $slotCounter++;

                    if ($curZeitSlot == null) {
                        fputcsv($handle, array( ''));
                    } else {

                        //$row = collect( ['Interviewee' => null,'ErstInterviewer*in' => null,'ZweitInterviewer*in' => null,'Protokollant*in' => null,'PriorityNumber' => null]);
                        $row['SlotNum'] = $slotCounter;
                        $row['RoomNum'] = $curZeitSlot->raumNummer;
                        $row['Interviewee'] = $curZeitSlot->interviewee->name;
                        $row['ErstInterviewer*in'] = $curZeitSlot->interviewerCombination->erst->interviewer->name;
                        $row['ZweitInterviewer*in'] = $curZeitSlot->interviewerCombination->zweit->interviewer->name;
                        $row['Protokollant*in'] = $curZeitSlot->interviewerCombination->protokoll->interviewer->name;
                        $row['PriorityNumber'] = $curZeitSlot->interviewerCombination->prioirityNumber;

                        //$row['Assign']    = $task->assign->name;
                        //$row['Description']    = $task->description;
                        //$row['Start Date']  = $task->start_at;
                        //$row['Due Date']  = $task->end_at;

                        //fputcsv($file, array($row['Title'], $row['Assign'], $row['Description'], $row['Start Date'], $row['Due Date']));
                        fputcsv($handle, array($row['SlotNum'],$row['RoomNum'],$row['Interviewee'], $row['ErstInterviewer*in'], $row['ZweitInterviewer*in'], $row['Protokollant*in'], $row['PriorityNumber']));
                    }
                }

            }

            fputcsv($handle, array(''));
            fputcsv($handle, array(''));
        }
        fclose($handle);

        print("Hello Here i am ");
        //download command
        return Response::download($filename, "download.csv", $headers);
    }


    public static function get_csvOLD()
    {
        $rot = new Rotationsplan();
        $collection = $rot->testRotationsPlanErstellen();

        $fileName = 'tasks.csv';

        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );

        $columns = array('Interviewee', 'ErstInterviewer*in', 'ZweitInterviewer*in', 'Protokollant*in', 'PriorityNumber');


        $callback = function () use ($collection, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            print("<br>Collection IST HERE: <br>");


            foreach ($collection as $day) {
                fputcsv($file, array(" ", " ", " ", " ", " "));
                fputcsv($file, array(" ", " ", " ", " ", " "));
                fputcsv($file, array("New Day", "___", "___", "___", "___"));

                foreach ($day as $slot) {

                    fputcsv($file, array(" ", " ", " ", " ", " "));
                    fputcsv($file, array("New Slot", "", "", "", ""));
                    foreach ($slot as $el) {
                        /** @var Zeitslot $curZeitSlot */
                        $curZeitSlot = $el;

                        print("ABDASDASASDASDA: " . $curZeitSlot . "<br><br>");

                        if ($curZeitSlot == null) {
                            fputcsv($file, array("", "", "", "", ""));
                        } else {

                            //$row = collect( ['Interviewee' => null,'ErstInterviewer*in' => null,'ZweitInterviewer*in' => null,'Protokollant*in' => null,'PriorityNumber' => null]);
                            $row['Interviewee'] = $curZeitSlot->interviewee->name;
                            $row['ErstInterviewer*in'] = $curZeitSlot->interviewerCombination->erst->interviewer->name;
                            $row['ZweitInterviewer*in'] = $curZeitSlot->interviewerCombination->zweit->interviewer->name;
                            $row['Protokollant*in'] = $curZeitSlot->interviewerCombination->protokoll->interviewer->name;
                            $row['PriorityNumber'] = $curZeitSlot->interviewerCombination->prioirityNumber;

                            //$row['Assign']    = $task->assign->name;
                            //$row['Description']    = $task->description;
                            //$row['Start Date']  = $task->start_at;
                            //$row['Due Date']  = $task->end_at;

                            //fputcsv($file, array($row['Title'], $row['Assign'], $row['Description'], $row['Start Date'], $row['Due Date']));
                            fputcsv($file, array($row['Interviewee'], $row['ErstInterviewer*in'], $row['ZweitInterviewer*in'], $row['Protokollant*in'], $row['PriorityNumber']));
                        }
                    }

                }


            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
