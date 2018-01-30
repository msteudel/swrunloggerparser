<?php
$tm = [
    'date' => 0,
    'dungeon' => 1,
    'result' => 2,
    'time' => 3,
];

$validDungeon = [
    'Necropolis B10',
    'Dragon\'s Lair B10',
    'Giant\'s Keep B10'
];


function processData( $inputFile ) {
    global $tm, $validDungeon;

    $runData = [];
    $row = 1;
    if (($handle = fopen($inputFile, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $num = count($data);
            $row++;

            if(in_array($data[$tm['dungeon']], $validDungeon)) {
                if( !is_array($runData[$data[$tm['dungeon']]][implode(',', sortTeam($data))])) {
                    $runData[$data[$tm['dungeon']]][implode(',', sortTeam($data))] = [
                        'win' => ($data[$tm['result']] == 'Win' ? 1 : 0),
                        'total' => 1,
                        'totalTime' => convertToSeconds($data[$tm['time']]),
                        'avgTime' => $data[$tm['time']]
                    ];

                    $runData[$data[$tm['dungeon']]][implode(',', sortTeam($data))]['avgWin'] = ($runData[$data[$tm['dungeon']]][implode(',', sortTeam($data))]['win'] / $runData[$data[$tm['dungeon']]][implode(',', sortTeam($data))]['total']) * 100;
                }
                else {
                    $runData[$data[$tm['dungeon']]][implode(',', sortTeam($data))]['total']++;
                    $runData[$data[$tm['dungeon']]][implode(',', sortTeam($data))]['totalTime'] += convertToSeconds($data[$tm['time']]);
                    if($data[$tm['result']] == 'Win'){
                        $runData[$data[$tm['dungeon']]][implode(',', sortTeam($data))]['win']++;
                    }
                    $runData[$data[$tm['dungeon']]][implode(',', sortTeam($data))]['avgWin'] = round(($runData[$data[$tm['dungeon']]][implode(',', sortTeam($data))]['win'] / $runData[$data[$tm['dungeon']]][implode(',', sortTeam($data))]['total']) * 100) . '%';
                    $runData[$data[$tm['dungeon']]][implode(',', sortTeam($data))]['avgTime'] = convertToMinutes($runData[$data[$tm['dungeon']]][implode(',', sortTeam($data))]['totalTime'] / $runData[$data[$tm['dungeon']]][implode(',', sortTeam($data))]['total']);
                }
            }


        }
        fclose($handle);
    }

    return $runData;
}

function sortTeam($data) {
    $team = [];

    $team[] = $data['21'];
    $team[] = $data['22'];
    $team[] = $data['23'];
    $team[] = $data['24'];
    sort($team);

    // put leader at front of array
    array_unshift($team, $data['20']);
    return $team;
}

function convertToSeconds($time) {
    $eTime = explode(':', $time);
    $totalSeconds = ($eTime['0'] * 60) + $eTime[1];

    return $totalSeconds;
}

function convertToMinutes($seconds) {
    return floor($seconds/60) . ":" . str_pad($seconds % 60, 2, "0", STR_PAD_LEFT);
}

function writeToCsv($data) {
    global $validDungeon, $outputFile;
    $fp = fopen($outputFile, 'w');
    fputcsv($fp, ['Dungeon', 'Team', 'Total Runs', 'Avg Time', 'Avg Win' ]);
    foreach($validDungeon as $dungeonName) {
        foreach($data[$dungeonName] as $teamName => $team) {
            $line = [$dungeonName, $teamName];

            unset($team['win']);
            unset($team['totalTime']);
            fputcsv($fp, array_merge($line, $team));
        }
    }
}

function writeToBrowser($data) {
    global $validDungeon, $outputFile;

    // Open the output stream
    $fh = fopen('php://output', 'w');

    // Start output buffering (to capture stream contents)
    ob_start();
    fputcsv($fh, ['Dungeon', 'Team', 'Total Runs', 'Avg Time', 'Avg Win' ]);

    foreach($validDungeon as $dungeonName) {
        foreach($data[$dungeonName] as $teamName => $team) {
            $line = [$dungeonName, $teamName];

            unset($team['win']);
            unset($team['totalTime']);
            fputcsv($fh, array_merge($line, $team));
        }
    }

    // Get the contents of the output buffer
    $string = ob_get_clean();

    // Set the filename of the download
    $filename = 'my_csv_' . date('Ymd') .'-' . date('His');

    // Output CSV-specific headers
    header('Pragma: public');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Cache-Control: private', false);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv";');
    header('Content-Transfer-Encoding: binary');

    // Stream the CSV data
    exit($string);

}

function cleanup($file) {
    unlink($file);
}

function createTable($data) {
    $html = '';
    if(is_array($data)) {
        foreach($data as $dungeon => $runData ) {
            $html .= '<h3>' . $dungeon . '</h3>';
            $html .= '<table class="table table-striped" data-toggle="table"  data-sort-name="numRuns" data-sort-order="desc">';
            $html .= '<thead><th data-field="team" data-sortable="true">Team Name</th>
                        <th data-field="numRuns" data-sortable="true">Number of Runs</th>
                        <th data-field="avgTime" data-sortable="true">Average Time</th>
                        <th data-field="winRate" data-sortable="true">Average Win Rate</th>
                        </thead>';
            $html .= '<tbody>';
            foreach($runData as $teamName => $teamData ) {
                unset($teamData['win']);
                unset($teamData['totalTime']);
                $html .= '<tr>';

                $html .= '<td>' . $teamName . '</td>';
                $html .= '<td>' . implode('</td><td>', $teamData ) . '</td>';

                $html .= '</tr>';
            }
            $html .= '</tbody>';
            $html .= '</table>';
        }
    }

    return $html;
}

if(isset($_POST['submitForm'])) {
    $uploaddir = dirname(__FILE__) . '/uploads/';
    $uploadfile = $uploaddir . microtime().'.csv';

    if (move_uploaded_file($_FILES['userFile']['tmp_name'], $uploadfile)) {
        $runData = processData($uploadfile);
        if($_GET['debug'] === 'true') {
            print_r($runData);exit;
        }
        if($_POST['downloadCsv']) {
            writeToBrowser($runData);
            cleanup($uploadFile);
        }

        $html = createTable($runData);

    } else {
        echo "Possible file upload attack!\n";
    }
}
?>
<!doctype html>

<html lang="en">
<head>
    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=UA-113126455-1"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', 'UA-113126455-1');
    </script>

    <meta charset="utf-8">

    <title>Dungeon Runs Processor</title>
    <meta name="description" content="Dungeon Teams">
    <meta name="author" content="HoboMiles">

    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>

    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootstrap-table/1.11.1/bootstrap-table.min.css">

    <!-- Latest compiled and minified JavaScript -->
    <script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-table/1.11.1/bootstrap-table.min.js"></script>

    <!-- Latest compiled and minified Locales -->
    <script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-table/1.11.1/locale/bootstrap-table-zh-CN.min.js"></script>
</head>

<body>
    <div class="container">
        <p>Upload your csv file that is generated from <a href="https://github.com/Xzandro/sw-exporter">SWEX</a>. If you need help using SWEX, here's a <a href="https://www.youtube.com/watch?v=2xwtDalvwp0">youtube video</a> that explains how to use it.</p>
        <!-- The data encoding type, enctype, MUST be specified as below -->
        <form enctype="multipart/form-data" action="index.php" method="POST">
            <!-- Name of input element determines name in $_FILES array -->
            <br />
            <br />

            <div class="form-group">
                <label for="userFile">Upload CSV: </label>
                <input name="userFile" type="file" class="form-control" />
            </div>

            <div class="form-group">
                <label for="downloadCsv">Download as CSV file: </label>
                <input name="downloadCsv" id="downloadCsv" type="checkbox"  class="form-control-file"/>
            </div>

            <input name="submitForm" type="submit" value="Upload File" class="btn btn-primary" />
        </form>

        <?php echo $html ?>
    </div>

</body>
</html>

