<?php

$csvFile = "RotationsPlan.csv";
header("Content-Type: text/csv; charset=UTF-16LE");
header("Content-Disposition: attachment;filename=$csvFile");
echo file_get_contents($csvFile);
readfile($csvFile);
exit();
