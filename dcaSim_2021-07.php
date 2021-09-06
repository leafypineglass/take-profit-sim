<?php
/*# DCA In and Take Profit Simulator
  By: u/callunquirka aka leafypineglass on github
  License: CC BY
	Version Created: 2021-07-29
	Updated: 2021-09-05
*/

/*## Notes
  Percentage above EMA
  RSI - Day and week
  Ichimoku clouds

  ## To Do
  Indicators for 1d candlesticks
    Change EMA names to fast, med, slow
  Use the weekly RSI?
  Add load function

*/

// ## Load
// config
include 'dcaSim_2021/config.php';
if ($disableExchange==1) {
  require 'vendor/autoload.php';
}

include 'binance-data-rest.php';
include 'indicators.php';

/* ## Calculate Indicators
  Loops through the ohlc/candlestick and calculates each entry individually
  Earlier entries are calculated differently
*/
function indicCalc ($filename, $ohlc, $emaPeriods, $RSIperiod) {
  for ($i = 0; $i <count($ohlc); $i++) {
    $open[$i] = $ohlc[$i]['open'];
		$high[$i] = $ohlc[$i]['high'];
		$low[$i] = $ohlc[$i]['low'];
		$close[$i] = $ohlc[$i]['close'];
		$volume[$i] = $ohlc[$i]['volume'];

    $indic[$i]['time'] = $ohlc[$i]['time'];

    //### EMA
    // loops through #emaPeriods and calculates each period, eg. #emaPeriods = Array(10, 21, 100);
    for ($iEMA = 0; $iEMA <count($emaPeriods); $iEMA++) {
      $emaName = 'ema'.$iEMA;
      $percentName = 'percent'.$emaName;
      if ($i>0) {
        $indic[$i][$emaName] = ema ($ohlc[$i]['close'], $indic[$i-1][$emaName], $emaPeriods[$iEMA]);
      } else {
        $indic[$i][$emaName] = $ohlc[$i]['close'];
      }
      $indic[$i][$percentName] = ($ohlc[$i]['close']-$indic[$i][$emaName])/$indic[$i][$emaName]*100;
    }

    //### RSI
    if ($i>0) {
      $RSIoutput = calcRSI ($RSIperiod, $ohlc[$i]['close'], $ohlc[$i-1]['close'], $indic[$i-1]['RSIup'], $indic[$i-1]['RSIdown']);
      $indic[$i]['RSIup'] = $RSIoutput['RSIup'];
			$indic[$i]['RSIdown'] = $RSIoutput['RSIdown'];
			$indic[$i]['RS'] = $RSIoutput['RS'];
			$indic[$i]['RSI'] = $RSIoutput['RSI'];
    } else {
      $indic[$i]['RSIup'] = 0;
			$indic[$i]['RSIdown'] = 0;
			$indic[$i]['RS'] = 1;
			$indic[$i]['RSI'] = 50;
    }
  }// end loop

  $filepath = 'dcaSim_2021/'.$filename.'-indic.json';
  file_put_contents($filepath, json_encode($indic));
  print ('Wrote File: '.$filepath.'</br>');

  return $indic;
}

/* ## Calculate DCA
  DCA in and out based on indicators
  Loops through each week
*/
function dcaCalc (
    $filename,
    $ohlc,
    $broadOHLC,
    $indic,
    $broadIndic,
    $dcaQuantity,
    $dcaFrequency,
    $spareDCA,
    $profitFactor,
    $extraProfitFactor,
    $reinvestFactor,
    $dcaRSI,
    $profitRSI,
    $reinvestRSI,
    $profitPercentEMA,
    $higherProfitPercentEMA,
    $reinvestPercentEMA,
    $profitFrequency,
    $reinvestFrequency
  ) {

  $firstDCADay = 21; // 21 because you want some loops for indicators to calculate a bit. This number is based on your indicator periods
  $assetQuantity = 0;
  $dcaCount = 0;
  $takeProfitCount = 0;
  $takeProfit = 0;
  $reinvestCount = 0;
  $lastDCA = 0;

  $profitWindow = array ('latest'=>0, 'count'=>0);
  $reinvestWindow = array ('latest'=>0, 'count'=>0);

  $minPercentEMA = array (0, 0, 0);
  $maxPercentEMA = array (0, 0, 0);
  $minRSI = 50;
  $maxRSI = 50;

  $averageBuyPrice = 0;
  $roughTaxableIncome = 0;

  // Find first week
  $broadTimes = array_column($broadOHLC, 'time');
  $broadInterval = 0;
  foreach ($broadTimes as $value) {
    if ($value <= $ohlc[$firstDCADay]['time']) {
      $broadInterval ++;
    } else {
      $broadInterval --;
      break;
    }
  }
  unset($value);
  unset($broadTimes);

  // Get week length and the time of next week
  $broadIntLength = $broadOHLC[1]['time']-$broadOHLC[0]['time'];
  $nextBroadTime = $broadIndic[$broadInterval]['time'];
  $broadIndicLast = count($broadOHLC)-1;
  $scheduled = $firstDCADay;
  $lastScheduled = 0;

  for ($i = $firstDCADay; $i <count($ohlc); $i++) {

    // Move to next week if necessary
    if ($ohlc[$i]['time']>=$nextBroadTime && $broadInterval<$broadIndicLast) {
      $broadInterval ++;
      $nextBroadTime = $broadIndic[$broadInterval]['time']+$broadIntLength;
    }

    $DCAedAlready = 0;
    $dcaViable = 0;
    $reinvestViable = 0;
    $profitViable = 0;


    //### Info Record

    for ($i2=0; $i2<3; $i2++) {
      $emaName = 'ema'.$i2;
      $currentPercentEMA = round(($ohlc[$i-1]['close']-$indic[$i-1][$emaName])/$indic[$i-1][$emaName]*100, 2, PHP_ROUND_HALF_UP);
      if ($currentPercentEMA<$minPercentEMA[$i2]) {
        $minPercentEMA[$i2] = $currentPercentEMA;
      }
      if ($currentPercentEMA>$maxPercentEMA[$i2]) {
        $maxPercentEMA[$i2] = $currentPercentEMA;
      }
    }

    if ($indic[$i-1]['RSI']<$minRSI) {
      $minRSI = round($indic[$i-1]['RSI'],2,PHP_ROUND_HALF_UP);
    }
    if ($indic[$i-1]['RSI']>$maxRSI) {
      $maxRSI = round($indic[$i-1]['RSI'],2,PHP_ROUND_HALF_UP);
    }

    $emaThreshold = ($profitPercentEMA/100)+1;
    $currentPercentEMA = ($ohlc[$i]['close']-$indic[$i-1]['ema2'])/$indic[$i-1]['ema2']*100;

    //### Check if DCA, Reinvest, or Take Profit are viable
    //#### DCA Conditions
    if ($indic[$i-1]['RSI']<$dcaRSI) {
      $dcaViable = 1;
    }

    //#### Reinvest conditions
    if ($indic[$i-1]['RSI']<$reinvestRSI) {
      $reinvestViable = 1;
    }

    if ($currentPercentEMA<$reinvestPercentEMA) {
      $reinvestViable = 1;
    }

    //#### DCA Out or takeProfit  conditions
    $profitSignals = 0;
    if ($indic[$i-1]['RSI']>$profitRSI && $indic[$i-1]['ema0']>$indic[$i-1]['ema1'] && $ohlc[$i]['open']>$indic[$i-1]['ema0']) {
      $profitViable = 1;
      $profitSignals++;
    }

    if ($currentPercentEMA>$profitPercentEMA) {
      $profitViable = 1;
      $profitSignals++;
    }

    if ($currentPercentEMA>$higherProfitPercentEMA) {
      $profitViable = 1;
      $profitSignals++;
    }

    // If take profit, don't DCA

    if ($profitViable==1) {
      $dcaViable = 0;
    }



    //if DCA in viable then DCA in

    if ($dcaViable == 1 && $i>=$lastDCA+$dcaFrequency && $i==$scheduled) {
      $toBuy = $dcaQuantity / $ohlc[$i]['open'];

      // Calculate average buy price of entire balance
      $newAveragePrice = $assetQuantity*$averageBuyPrice + $toBuy*$ohlc[$i]['open'];
  		$newAveragePrice = $newAveragePrice/($assetQuantity + $toBuy);
  		$newAveragePrice = round($newAveragePrice,5, PHP_ROUND_HALF_UP);
      $averageBuyPrice = $newAveragePrice;

      // Add to balance
      $assetQuantity += $toBuy;
      $dcaCount ++;
      $DCAedAlready ++;
      $lastDCA = $i;
      //double DCA size if you haven't DCA'ed every week
      if ($dcaCount< $i/$dcaFrequency-1 && $spareDCA ==1) {
        $assetQuantity += $toBuy;
        $dcaCount ++;
      }
    }

    // Set the next DCA schedule day
    if ($i==$scheduled) {
      $lastScheduled = $scheduled;
      $scheduled = $i+$dcaFrequency;
    }



    if ($reinvestViable == 1
    && $reinvestWindow['latest']<=$i-$reinvestFrequency) {
      //reinvest if have available fiat
      $reinvestValue = ($dcaQuantity*$reinvestFactor);
      if ($reinvestValue<=$takeProfit) {
        $reinvestWindow['latest'] = $i;
        // everytime you reinvest, you reinvest $profitFactor number of chunks

        $toBuy = $reinvestValue / $ohlc[$i]['open'];

        // Calculate average buy price of entire balance after reinvestment
        $newAveragePrice = $assetQuantity*$averageBuyPrice + $toBuy*$ohlc[$i]['open'];
    		$newAveragePrice = $newAveragePrice/($assetQuantity + $toBuy);
    		$newAveragePrice = round($newAveragePrice,5, PHP_ROUND_HALF_UP);
        $averageBuyPrice = $newAveragePrice;

        $assetQuantity += $reinvestValue/$ohlc[$i]['open'];
        $takeProfit -= $reinvestValue;
        //$dcaCount ++;
        $reinvestCount+=1;
      }
    }

    //if profitViable and take profit wouldn't be too frequent

    $profitQuantity = $dcaQuantity*$profitFactor/$ohlc[$i]['open'];
    if ($profitViable ==1
    && $assetQuantity>$profitQuantity
    && $profitWindow['latest']<=$i-$profitFrequency
    ) {
      $profitWindow['latest']=$i;

      // everytime you takeProfit, you takeProfit in $profitFactor number of chunks
      $profitValue = $dcaQuantity*$profitFactor;
      $assetQuantity -= $profitQuantity;
      $takeProfitCount +=1;
      $takeProfit += $profitValue;

      // estimate taxable income based on average buy price
      $tempProfit = $profitQuantity*$ohlc[$i]['open']-$profitQuantity*$averageBuyPrice;
      $roughTaxableIncome +=$tempProfit;
      unset ($tempProfit);

      //take extra profit if conditions are right
      if ($profitSignals>1) {
        $profitValue = $dcaQuantity*($extraProfitFactor);
        $profitQuantity = $profitValue/$ohlc[$i]['open'];
        $assetQuantity -= $profitQuantity;
        $takeProfitCount +=1;
        $takeProfit += $profitValue;

        print ('Extra Take Profit (Another $'.$profitValue.')<br>');

        // estimate taxable income based on average buy price
        $tempProfit = $profitQuantity*$ohlc[$i]['open']-$profitQuantity*$averageBuyPrice;
        $roughTaxableIncome +=$tempProfit;
        unset ($tempProfit);
      }
    }

  }// end loop
  $lastDay = count ($ohlc)-1;
  $lastDate = date('Y-m-d', $ohlc[$lastDay]['time']);
  $assetValue = $assetQuantity*$ohlc[$lastDay]['close'];
  $total = $assetValue+$takeProfit;

  // Calculate EMA % of last day:
  for ($i2=0; $i2<3; $i2++) {
    $emaName = 'ema'.$i2;
    $currentPercentEMA = round(($ohlc[$lastDay]['close']-$indic[$lastDay][$emaName])/$indic[$lastDay][$emaName]*100, 2, PHP_ROUND_HALF_UP);
    $lastEMA[$i2] = $currentPercentEMA;
  }

  print ('<p>');
  print ('Last Day: '.$lastDate.'<br>');
  print ('Days: '.count($ohlc).'<br>');
  print ('Last RSI: '.$indic[$lastDay]['RSI'].'<br>');
  print ('Last Price relative to EMA in %:<br>');
  print_r ($lastEMA);

  print ('</p><p>');
  print ('Lowest RSI: '.$minRSI.'<br>');
  print ('Highest RSI: '.$maxRSI.'<br>');
  print ('Lowest Price relative to EMA in %:<br>');
  print_r ($minPercentEMA);
  print ('<br>');
  print ('Highest Price relative to EMA in %:<br>');
  print_r ($maxPercentEMA);
  print ('</p>');

  print ('<p>');
  print ('Asset Quantity: '.$assetQuantity.'<br>');
  print ('Asset Value: '.$assetValue.'<br>');
  print ('</p><p>');
  print ('DCA Count: '.$dcaCount.'<br>');
  print ('DCA Value: '.$dcaCount*$dcaQuantity.'<br>');
  print ('Profit Awaiting Reinvestment: '.$takeProfit.'<br>');
  print ('Take Profit Count: '.$takeProfitCount.'<br>');
  print ('Reinvest Count: '.$reinvestCount.'<br>');
  print ('</p><p>');
  print ('Total: '.$total.'<br>');
  print ('Rough Taxable Income: '.$roughTaxableIncome.'<br>');
  print ('10% Tax: '.$roughTaxableIncome*0.1.'<br>');
  print ('</p>');
}

$unixTime = time();
$date = date('Y-m-d', $unixTime);
$daySeconds = 86400*365*2;
$since = $unixTime-$daySeconds;

// ### Run OHLC and Indicator functions


// get new candlesticks and calculate indicators
if ($getNewData ==1){
  $api = new Binance\API($key,$secret);
  $ohlc = $api->candlesticks($pair, "1d", $since, null, null, 1000);
  $ohlc = ohlcRekey ($ohlc);

  $filepath = 'dcaSim_2021/daily-OHLC.json';
  file_put_contents($filepath, json_encode($ohlc));
  print ('Wrote File: '.$filepath.'</br>');

  $filepath = 'dcaSim_2021/'.$date.'-daily-OHLC.csv';
	$csv_file = new SplFileObject($filepath, 'w');
	foreach ($ohlc as $result) {
		 $csv_file->fputcsv($result, ',');
	}

  $indic = indicCalc ('daily', $ohlc, $emaPeriods, $RSIperiod);

  $filepath = 'dcaSim_2021/'.$date.'-daily-indic.csv';
	$csv_file = new SplFileObject($filepath, 'w');
	foreach ($indic as $result) {
		 $csv_file->fputcsv($result, ',');
	}

  $broadOHLC = $api->candlesticks($pair, "1w", $since, null, null, null);
  $broadOHLC = ohlcRekey ($broadOHLC);
  $broadIndic = indicCalc ('weekly', $broadOHLC, $emaPeriods, $RSIperiod);

  $filepath = 'dcaSim_2021/'.$date.'-weekly-OHLC.csv';
	$csv_file = new SplFileObject($filepath, 'w');
	foreach ($broadOHLC as $result) {
		 $csv_file->fputcsv($result, ',');
	}
  $filepath = 'dcaSim_2021/'.$date.'-weekly-indic.csv';
	$csv_file = new SplFileObject($filepath, 'w');
	foreach ($broadIndic as $result) {
		 $csv_file->fputcsv($result, ',');
	}

  $filepath = 'dcaSim_2021/weekly-OHLC.json';
  file_put_contents($filepath, json_encode($broadOHLC));
  print ('Wrote File: '.$filepath.'</br>');
} else {
  // if $getNewData = 0 then just load the old files
  $ohlc = file_get_contents('dcaSim_2021/daily-OHLC.json');
  $ohlc = json_decode($ohlc, true);
  $indic = file_get_contents('dcaSim_2021/daily-indic.json');
  $indic = json_decode($indic, true);

  $broadOHLC = file_get_contents('dcaSim_2021/weekly-OHLC.json');
  $broadOHLC = json_decode($broadOHLC, true);
  $broadIndic = file_get_contents('dcaSim_2021/weekly-indic.json');
  $broadIndic = json_decode($broadIndic, true);
}

// ### Run DCA calc function

dcaCalc (
  $filename,
  $ohlc,
  $broadOHLC,
  $indic,
  $broadIndic,
  $dcaQuantity,
  $dcaFrequency,
  $spareDCA,
  $profitFactor,
  $extraProfitFactor,
  $reinvestFactor,
  $dcaRSI,
  $profitRSI,
  $reinvestRSI,
  $profitPercentEMA,
  $higherProfitPercentEMA,
  $reinvestPercentEMA,
  $profitFrequency,
  $reinvestFrequency
);

?>
