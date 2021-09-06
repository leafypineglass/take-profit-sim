<?php
  //Only Binance is supported at the moment

  $key = "keyHere";
  $secret = "secretHere";
  $pair = 'BTCUSDT';
  $enableExchange = 0;

  //Get new candlesticks from API or use the ones from the saved file
  $getNewData = 0;

  // Output filename
  $filename = 'test';

  $emaPeriods = array (10, 21, 200);
  $RSIperiod = 14;

  // dollars per DCA chunk
  $dcaQuantity = 10;
  // DCA every $dcaFrequency number of days
  $dcaFrequency = 14;
  // double DCA in this week if previous scheduled days were not viable (0 or 1)
  $spareDCA = 1;

  // multiply by $dcaQuantity to get value for take profit or reinvest (set to 0 to disable)
  $profitFactor = 5;
  $extraProfitFactor = 5;
  $reinvestFactor = 5;

  // days between previous take profit or reinvestment
  $profitFrequency = 3;
  $reinvestFrequency = 3;

  // thresholds for RSI (set to impossible number to disable, RSI goes from 0-100)
  $dcaRSI = 100; // DCA in when less than this number
  $profitRSI = 200;
  $reinvestRSI = 0;

  /* Take profit or Reinvest when yesterday's close price is % relative yesterday's slowest EMA
  (set to impossible number to disable)
   Calculated as (close-EMA)/EMA*100
   If using tradingview or cryptowat.ch measurement tools, draw from the EMA line to close. If you draw the opposite direction you get very different numbers.
   $higherProfitPercentEMA is if you want to take more profit when the price is way high.
  */
  $profitPercentEMA = 85;
  $higherProfitPercentEMA = 600;
  $reinvestPercentEMA = -10;

?>
