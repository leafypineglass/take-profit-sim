# Take Profit Sim
By: u/callunquirka aka leafypineglass on github

License: CC BY

Grabs backdata from Binance, calculates RSI and EMA's. Then it simulates DCA, take profit, and reinvesting using this data and user configed conditions.

## Dependencies
PHP 7

Webserver/Local dev environment, eg XAMPP

If you want update data: Binance API from jaggedsoft

https://github.com/jaggedsoft/php-binance-api


## Install
If you just want to use the old data supplied you can set $getNewData = 0 and $enableExchange = 0. Add your API key and secret. DISABLE WITHDRAWALS FOR THE KEY. Run on local dev platform, with $getNewData set to whether you want it to extract data.

## Use
Run it in your local dev environment or webserver. Refresh page every time you change config.

## config
Contains the all the settings to run simulation.

## Includes
### binance-data-rest.php
Restructures and rekeys data from API into a format useable by the bot.

### indicators.php
Includes the math for EMA and RSI.
