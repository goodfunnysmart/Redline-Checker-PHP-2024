<?php
function fetchData($symbol) {
    $apiKey = 'YOUR_ALPHA_VANTAGE_API_KEY';
    $symbol = str_replace('.AX', '.ASX', $symbol); // Adjust symbol format for Alpha Vantage
    $url = "https://www.alphavantage.co/query?function=TIME_SERIES_DAILY_ADJUSTED&symbol={$symbol}&apikey={$apiKey}&outputsize=full&datatype=csv";
    $cacheFile = __DIR__ . "/cache/{$symbol}.csv";
    $cacheTime = 3600; // 1 hour

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
        return file_get_contents($cacheFile);
    }

    $data = @file_get_contents($url);

    if ($data !== false) {
        file_put_contents($cacheFile, $data);
        return $data;
    }

    throw new Exception("Failed to fetch data for $symbol");
}

function calculateEMA($data, $period = 65) {
    $ema = [];
    $multiplier = 2 / ($period + 1);
    $prices = array_filter(array_column($data, 'close'), 'is_numeric'); // Ensure only numeric values are included

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
    'Current Portfolio' => ['^AXJO', 'AZJ.AX', 'BHP.AX', 'GDX.AX', 'HVN.AX', 'MYR.AX', 'NST.AX', 'PSC.AX', 'RIO.AX', 'STO.AX', 'WBC.AX', 'WES.AX'],
    'Dreamteam' => ['AWC.AX', 'ANG.AX', 'BGA.AX', 'DRO.AX', 'DUG.AX', 'FND.AX', 'GTK.AX', 'GNP.AX', 'GMG.AX', 'GQG.AX', 'KPG.AX', 'LOV.AX', 'MRM.AX', 'REG.AX', 'RUL.AX', 'SFR.AX', 'SIG.AX', 'SKS.AX', 'TOP.AX', 'TUA.AX', 'URW.AX', 'UNI.AX', 'VEE.AX', 'WTC.AX', 'ZIP.AX']
];

$emailContent = "<html><head><title>Stock Status</title><style>
    table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }
    th, td { padding: 8px; text-align: center; border: 1px solid lightgrey; }
    th { background-color: #f2f2f2; }
    .light-green { background-color: #ccffcc; }
    .light-red { background-color: #ffcccc; }
</style></head><body>";

$sendEmail = false;

function processPortfolio($portfolio, $portfolioName, &$sendEmail, &$emailContent) {
    $emailContent .= "<h1>$portfolioName</h1><table><tr><th>Stock</th><th>Yesterday</th><th>Price</th><th>15-day EMA</th><th>65-day EMA</th><th>% Difference</th><th>BUY 'x' Shares</th></tr>";
    echo "<h1>$portfolioName</h1><table style='border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;'><tr><th style='padding: 8px; text-align: center; border: 1px solid lightgrey; background-color: #f2f2f2;'>Stock</th><th style='padding: 8px; text-align: center; border: 1px solid lightgrey; background-color: #f2f2f2;'>Yesterday</th><th style='padding: 8px; text-align: center; border: 1px solid lightgrey; background-color: #f2f2f2;'>Price</th><th style='padding: 8px; text-align: center; border: 1px solid lightgrey; background-color: #f2f2f2;'>15-day EMA</th><th style='padding: 8px; text-align: center; border: 1px solid lightgrey; background-color: #f2f2f2;'>65-day EMA</th><th style='padding: 8px; text-align: center; border: 1px solid lightgrey; background-color: #f2f2f2;'>% Difference</th><th style='padding: 8px; text-align: center; border: 1px solid lightgrey; background-color: #f2f2f2;'>BUY 'x' Shares</th></tr>";

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

            $ema15 = calculateEMA($data, 15);
            $ema65 = calculateEMA($data, 65);
            $latestClose = end($data)['Close'];
            $yesterdayClose = prev($data)['Close'];
            $latestEma15 = end($ema15);
            $latestEma65 = end($ema65);

            $formattedClose = '$' . number_format((float)$latestClose, 2, '.', '');
            $formattedEma15 = '$' . number_format((float)$latestEma15, 2, '.', '');
            $formattedEma65 = '$' . number_format((float)$latestEma65, 2, '.', '');
            $formattedYesterdayClose = '$' . number_format((float)$yesterdayClose, 2, '.', '');

            $percentageDifference = (($latestClose - $latestEma65) / $latestEma65) * 100;
            $formattedPercentageDifference = number_format($percentageDifference, 2, '.', '') . '%';

            $newColumnValue = floor(50 / ($latestClose - $latestEma65));
            $formattedNewColumnValue = number_format($newColumnValue);

            if ($latestClose > $latestEma15) {
                $color = 'green';
            } elseif ($latestClose > $latestEma65) {
                $color = 'orange';
            } else {
                $color = 'red';
            }

            if ($yesterdayClose > $latestEma15) {
                $yesterdayColor = 'green';
            } elseif ($yesterdayClose > $latestEma65) {
                $yesterdayColor = 'orange';
            } else {
                $yesterdayColor = 'red';
            }

            $rowClass = '';
            $rowStyle = '';
            if ($yesterdayColor == 'orange' && $color == 'green') {
                $rowClass = 'light-green';
                $rowStyle = 'background-color: #ccffcc;';
            } elseif (($yesterdayColor == 'orange' || $yesterdayColor == 'green') && $color == 'red') {
                $rowClass = 'light-red';
                $rowStyle = 'background-color: #ffcccc;';
            }

            // Update the stock URL to use .ASX instead of .AX
            $stockUrl = "https://www.google.com/finance/quote/" . str_replace('.AX', ':ASX', $stock) . "?hl=en";
            $stockLink = "<a href='$stockUrl' target='_blank'>$stock</a>";

            $emailContent .= "<tr class='$rowClass' style='color: $color; $rowStyle'><td>$stockLink</td><td style='color: $yesterdayColor;'>$formattedYesterdayClose</td><td>$formattedClose</td><td>$formattedEma15</td><td>$formattedEma65</td><td>$formattedPercentageDifference</td><td>$formattedNewColumnValue</td></tr>";
            echo "<tr class='$rowClass' style='color: $color; $rowStyle'><td style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>$stockLink</td><td style='padding: 8px; text-align: center; border: 1px solid lightgrey; color: $yesterdayColor;'>$formattedYesterdayClose</td><td style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>$formattedClose</td><td style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>$formattedEma15</td><td style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>$formattedEma65</td><td style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>$formattedPercentageDifference</td><td style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>$formattedNewColumnValue</td></tr>";

            if ($color !== 'green') {
                $sendEmail = true;
            }
        } catch (Exception $e) {
            $emailContent .= "<tr style='color: red;'><td colspan='7'>$stock: Error - " . $e->getMessage() . "</td></tr>";
            echo "<tr style='color: red;'><td colspan='7' style='padding: 8px; text-align: center; border: 1px solid lightgrey;'>$stock: Error - " . $e->getMessage() . "</td></tr>";
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
    $to = "redline@greache.com";
    $subject = "Stock Alert";
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: mail@greache.com' . "\r\n";

    mail($to, $subject, $emailContent, $headers);
}
?>
