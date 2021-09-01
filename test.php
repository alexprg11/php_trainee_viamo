<?php
require_once "client.class.php";

$db = new DB();
$initBanknotes = array(
    10 => 10,
    20 => 10,
    50 => 1,
    100 => 10,
    200 => 10,
    500 => 10,
    1000 => 5
);
$storage = new Storage($initBanknotes);
$log = new Logging();
$client = new Client($storage, $log);

$ret = $client->getCard(1111222233334444);

$ret = $client->getPin(1133);
$ret = $client->getPin(1144);
$ret = $client->getPin(1134);
$ret = $client->getPin(1155);
$ret = $client->getPin(1122);


$ret = $client->getCard(1111222233334444);
$ret = $client->getPin(1234);

$balance = $client->getBalanceByCard();
echo "Баланс: " . $balance. PHP_EOL;

$putArray = array(100, 100, 50, 10, 500, 500, 500);
$ret = $client->putMoney($putArray);

$ret = $client->getMoney(860);


$ret = $client->getMoney(2530);
var_dump($ret);

$client->transfer(2000000,2222333344445555);
$client->transfer(220,2222333344445555);

$client->logOff();

$client->freeTransfer(array(200,10,50,100,100,500), 2222333344445555);