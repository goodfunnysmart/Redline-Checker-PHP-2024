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

echo "<html><head><style>
    .green { color: green; }
    .red { color: red; }
</style></head><body><h1>Australian Stock Status</h1><ul>";

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
            echo "<li class='red'>$stock: Close = $formattedClose, 65-day EMA = $formattedEma</li>";
        } else {
            echo "<li class='green'>$stock: Close = $formattedClose, 65-day EMA = $formattedEma</li>";
        }
    } catch (Exception $e) {
        echo "<li class='red'>$stock: Error - " . $e->getMessage() . "</li>";
    }
}

echo "</ul></body></html>";
?>
