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

$stocks = [
    'Current Portfolio' => ['AZJ.AX', 'BHP.AX', 'GDX.AX', 'HVN.AX', 'MYR.AX', 'NST.AX', 'PSC.AX', 'RIO.AX', 'STO.AX', 'WBC.AX', 'WES.AX'],
    'Dreamteam' => ['AWC.AX', 'ANG.AX', 'BGA.AX', 'DRO.AX', 'DUG.AX', 'FND.AX', 'GTK.AX', 'GNP.AX', 'GMG.AX', 'GQG.AX', 'KPG.AX', 'LOV.AX', 'MRM.AX', 'REG.AX', 'RUL.AX', 'SFR.AX', 'SIG.AX', 'SKS.AX', 'TOP.AX', 'TUA.AX', 'URW.AX', 'UNI.AX', 'VEE.AX', 'WTC.AX', 'ZIP.AX']
];

$emailContent = "<html><head><title>Stock Status</title></head><body>";

$sendEmail = false;

function processPortfolio($portfolio, $portfolioName, &$sendEmail, &$emailContent) {
    $emailContent .= "<h1>$portfolioName</h1><table style='border-collapse: collapse; width: 100%;'><tr><th style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>Stock</th><th style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>Price</th><th style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>15-day EMA</th><th style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>65-day EMA</th></tr>";
    echo "<h1>$portfolioName</h1><table style='border-collapse: collapse; width: 100%;'><tr><th style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>Stock</th><th style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>Price</th><th style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>15-day EMA</th><th style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>65-day EMA</th></tr>";

    foreach ($portfolio as $stock) {
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

            $ema15 = calculateEMA($data, 21);
            $ema65 = calculateEMA($data, 91);
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

            $emailContent .= "<tr style='color: $color;'><td style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>$stock</td><td style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>$formattedClose</td><td style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>$formattedEma15</td><td style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>$formattedEma65</td></tr>";
            echo "<tr style='color: $color;'><td style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>$stock</td><td style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>$formattedClose</td><td style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>$formattedEma15</td><td style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>$formattedEma65</td></tr>";

            if ($color !== 'green') {
                $sendEmail = true;
            }
        } catch (Exception $e) {
            $emailContent .= "<tr style='color: red;'><td colspan='4' style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>$stock: Error - " . $e->getMessage() . "</td></tr>";
            echo "<tr style='color: red;'><td colspan='4' style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>$stock: Error - " . $e->getMessage() . "</td></tr>";
        }
    }

    $emailContent .= "</table>";
    echo "</table>";
}

foreach ($stocks as $portfolioName => $portfolio) {
    processPortfolio($portfolio, $portfolioName, $sendEmail, $emailContent);
}

$emailContent .= "</body></html>";

echo "</body></html>";

if ($sendEmail) {
    $to = "david@goodfunnysmart.com";
    $subject = "Stock Alert";
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: mail@greache.com' . "\r\n";

         mail($to, $subject, $emailContent, $headers);
         }
         ?>
