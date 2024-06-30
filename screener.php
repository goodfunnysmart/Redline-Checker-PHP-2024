<?php
function fetchData($symbol) {
    $url = "https://query1.finance.yahoo.com/v7/finance/download/{$symbol}?period1=0&period2=" . time() . "&interval=1d&events=history&includeAdjustedClose=true";

    $options = [
        'http' => [
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3\r\n"
        ]
    ];

    $context = stream_context_create($options);
    $data = @file_get_contents($url, false, $context); // Suppress errors with @

    if ($data === FALSE) {
        $error = error_get_last();
        throw new Exception("Failed to fetch data for $symbol: " . $error['message']);
    }

    return $data;
}

function getSixMonthsAgoDate() {
    return strtotime('-6 months');
}

function calculatePriceChange($data) {
    $latestClose = (float)end($data)['Close'];
    $sixMonthsAgoClose = null;
    $sixMonthsAgoTimestamp = getSixMonthsAgoDate();

    foreach (array_reverse($data) as $dayData) {
        if (strtotime($dayData['Date']) <= $sixMonthsAgoTimestamp) {
            $sixMonthsAgoClose = (float)$dayData['Close'];
            break;
        }
    }

    if ($sixMonthsAgoClose === null || $sixMonthsAgoClose == 0) {
        throw new Exception('Not enough data or invalid data to calculate 6 months ago price');
    }

    $percentageChange = (($latestClose - $sixMonthsAgoClose) / $sixMonthsAgoClose) * 100;
    return $percentageChange;
}

function readSymbolsFromCsv($filename) {
    $symbols = [];
    if (($handle = fopen($filename, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $symbols[] = $data[0];
        }
        fclose($handle);
    }
    return $symbols;
}

$symbols = readSymbolsFromCsv('asx_symbols.csv');

echo "<html><head><title>Stock Screener</title><style>
    body { font-family: Arial, sans-serif; }
    a { text-decoration: none; color: blue; }
    a:hover { text-decoration: underline; }
</style></head><body>";
echo "<h1>Stock Screener Results</h1><ul>";

foreach ($symbols as $symbol) {
    try {
        $csv = fetchData($symbol);
        if (!$csv) {
            throw new Exception("No data returned for $symbol");
        }

        $lines = explode("\n", trim($csv));
        $headers = str_getcsv(array_shift($lines));

        $data = array_map(function($line) use ($headers) {
            return array_combine($headers, str_getcsv($line));
        }, $lines);

        $priceChange = calculatePriceChange($data);

        if ($priceChange >= 50) {
            $stockUrl = "https://www.google.com/finance/quote/" . str_replace('.AX', ':ASX', $symbol) . "?hl=en";
            $stockLink = "<a href='$stockUrl' target='_blank'>$symbol</a>";
            echo "<li>$stockLink</li>";
        }
    } catch (Exception $e) {
        echo "<li style='color: red;'>$symbol: Error - " . $e->getMessage() . "</li>";
    }
}

echo "</ul></body></html>";
?>
