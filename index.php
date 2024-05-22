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

$emailContent = "<html><head><title>Australian Stock Status</title></head><body><h1>Australian Stock Status</h1><ul>";

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

        $ema15 = calculateEMA($data, 15);
        $ema65 = calculateEMA($data, 65);
        $latestClose = end($data)['Close'];
        $latestEma15 = end($ema15);
        $latestEma65 = end($ema65);

        $formattedClose = '$' . number_format((float)$latestClose, 2, '.', '');
        $formattedEma15 = '$' . number_format((float)$latestEma15, 2, '.', '');
        $formattedEma65 = '$' . number_format((float)$latestEma65, 2, '.', '');

        if ($latestClose > $latestEma15) {
            $color = 'green';
        } elseif ($latestClose > $latestEma65) {
            $color = 'orange';
        } else {
            $color = 'red';
        }

        $emailContent .= "<li style='color: $color;'>$stock: Close = $formattedClose, 15-day EMA = $formattedEma15, 65-day EMA = $formattedEma65</li>";
        echo "<li style='color: $color;'>$stock: Close = $formattedClose, 15-day EMA = $formattedEma15, 65-day EMA = $formattedEma65</li>";

        if ($color !== 'green') {
            $sendEmail = true;
        }
    } catch (Exception $e) {
        $emailContent .= "<li style='color: red;'>$stock: Error - " . $e->getMessage() . "</li>";
        echo "<li style='color: red;'>$stock: Error - " . $e->getMessage() . "</li>";
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
