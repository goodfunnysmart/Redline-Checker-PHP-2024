<?php
function fetchData($symbol) {
    $url = "https://query1.finance.yahoo.com/v7/finance/download/{$symbol}?period1=0&period2=" . time() . "&interval=1d&events=history&includeAdjustedClose=true";
    return file_get_contents($url);
}

function calculateEMA($data, $period = 65) {
    $ema = [];
    $multiplier = 2 / ($period + 1);
    $prices = array_filter(array_column($data, 'Close'), 'is_numeric'); // Ensure only numeric values are included

    if (count($prices) < $period) {
        throw new Exception('Not enough data to calculate EMA');
    }

    $ema[] = array_shift($prices);

    foreach ($prices as $price) {
        $ema[] = ($price - end($ema)) * $multiplier + end($ema);
    }

    return $ema;
}

$stocks = ['AZJ.AX', 'BHP.AX', 'GDX.AX', 'HVN.AX', 'MYR.AX', 'NST.AX', 'PSC.AX', 'RIO.AX', 'STO.AX', 'WBC.AX', 'WES.AX'];

$emailContent = "<html><head><style>
    .green { color: green; }
    .red { color: red; }
</style></head><body><h1>Australian Stock Status</h1><ul>";

$sendEmail = false;

foreach ($stocks as $stock) {
    try {
        $csv = fetchData($stock);
        if (!$csv) {
            throw new Exception("Failed to fetch data for $stock");
        }

        $lines = explode("\n", trim($csv));
        $headers = str_getcsv(array_shift($lines));

        $data = array_map(function($line) use ($headers) {
            return array_combine($headers, str_getcsv($line));
        }, $lines);

        $ema = calculateEMA($data);
        $latestClose = end($data)['Close'];
        $latestEma = end($ema);

        $formattedClose = '$' . number_format((float)$latestClose, 2, '.', '');
        $formattedEma = '$' . number_format((float)$latestEma, 2, '.', '');

        if ($latestClose < $latestEma) {
            $emailContent .= "<li class='red'>$stock: Close = $formattedClose, 65-day EMA = $formattedEma</li>";
            $sendEmail = true;
            echo "<li class='red'>$stock: Close = $formattedClose, 65-day EMA = $formattedEma</li>";
        } else {
            $emailContent .= "<li class='green'>$stock: Close = $formattedClose, 65-day EMA = $formattedEma</li>";
            echo "<li class='green'>$stock: Close = $formattedClose, 65-day EMA = $formattedEma</li>";
        }
    } catch (Exception $e) {
        $emailContent .= "<li class='red'>$stock: Error - " . $e->getMessage() . "</li>";
        echo "<li class='red'>$stock: Error - " . $e->getMessage() . "</li>";
    }
}

$emailContent .= "</ul></body></html>";

if ($sendEmail) {
    $to = "david@goodfunnysmart.com";
    $subject = "Stock Alert";
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: mail@greache.com' . "\r\n";

    mail($to, $subject, $emailContent, $headers);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Stock Status</title>
    <style>
        .green { color: green; }
        .red { color: red; }
        #spinner {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
        }
        .spinner-border {
            width: 4rem;
            height: 4rem;
        }
    </style>
    <script>
        window.onload = function() {
            document.getElementById('spinner').style.display = 'none';
        };
    </script>
</head>
<body>
<div id="spinner">
    <div class="spinner-border" role="status">
        <span class="sr-only">Loading...</span>
    </div>
</div>
</body>
</html>
