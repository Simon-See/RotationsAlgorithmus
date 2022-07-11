<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<body>
<h1>Download the csv</h1>
<?php

function downloadCSV()
{
    //echo 'Have a great day' . '<br>';
    ob_start();
    $csvFile = "RotationsPlan.csv";
    header("Content-Type: text/csv; charset=UTF-16LE");
    header("Content-Disposition: attachment;filename=$csvFile");
    echo file_get_contents($csvFile);
    //readfile($csvFile);
    exit();
}
if (isset($_GET['downloadCSV'])) {
    downloadCSV();
}

?>

<button type="button" onclick="location.href='index.php?downloadCSV=true'">Download CSV</button>


</body>

</html>
