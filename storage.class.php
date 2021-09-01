<?php

/**
* Class for operations with banknotes in ATM
*/
class StorageException extends Exception { }

function lackStorageException() {
    throw new StorageException('There is not enough money in the storage');
}
function inappropriateStorageException() {
    throw new StorageException('Inappropriate sum, change it to get money');
}

class Storage {
    static public $banknotes = [
        1 => 1000, 
        2 => 500,
        3 => 200,
        4 => 100,
        5 => 50,
        6 => 20,
        7 => 10
    ];
    /** assoc array with key self::$banknotes & values - quantity of banknotes  */
    private $storage = array();
    private $storageSum = 0;

    /**
    *  @param: assoc array of banknotes: [banknote => count]
    */
    public function __construct(array $banknotes) {
        $this->setStorage($banknotes);
    }

    /**
    * Saving the results of banknote recalculation
    * @param array $banknotes - array [banknote => count]
    * @return int - sum all banknontes in storage
    */
    private function setStorage(array $banknotes) {
        $this->storage = array();
        $this->storageSum = 0;
        foreach ($banknotes as $banknote => $quantity) {
            if (in_array($banknote, Storage::$banknotes)) {
                $this->storage[$banknote] = $quantity;
                $this->storageSum += $quantity * $banknote;
            }
        }
        return $this->storageSum;
    }

    /**
     * Getting a banknote array
     * 
     * @param int $sum - chargeable amount
     * @return  array of banknotes [banknote => quantity]
     *          or false when rest of sum > 0
     */
    private function getQuantityBanknote(int $sum) {
        $quantity = array();
        foreach (self::$banknotes as $key => $banknote) {
            if ($sum >= $banknote && $sum/$banknote > 0) {
                $needQuantities = (int)($sum/$banknote);
                if ($needQuantities <= $this->storage[$banknote]) {
                    $quantity[$banknote] = $needQuantities;
                    $sum = $sum % $banknote;
                } else {
                    $quantity[$banknote] = $this->storage[$banknote];
                    $sum = $sum - $banknote * $quantity[$banknote];
                }
            }
        }
        if ($sum > 0) return false;

        return $quantity;
    }

    /**
     * Checking the possibility of obtaining the amount of banknotes from the storage
     * @param  int $sum - chargeable amount
     * @return: Assoc array of banknotes with quantity [ banknote => quantity] or empty array
     */
    public function checkSum(int $sum) {
        if ($sum > $this->storageSum) {
            throw lackStorageException();
        }

        $banknotes = $this->getQuantityBanknote($sum);
        if ($banknotes === false) {
            throw inappropriateStorageException();
        }
        
        return $banknotes;
    }

    /**
    *    Return the array with banknotes after deduction from the vault
    */
    public function getSum(int $sum) {
        $arrayOfBanknotes = $this->checkSum($sum);
        
        if ($arrayOfBanknotes) {
            foreach($arrayOfBanknotes as $banknote => $quantity) {
                $this->storage[$banknote] -= $quantity;
                $this->storageSum -= $quantity * $banknote;
            }
            return $arrayOfBanknotes;
        }
        return false;
    }

    /**
     * Put client's banknotes in storage
     * Assume that the terminal accepts banknotes only of a given denomination
     * Need to ckeck free space in storage!
     */
    public function putSum(array $banknotes) {
        $addSum = 0;
        foreach($banknotes as $banknote => $quantity) {
            if (in_array($banknote, self::$banknotes)) {
                $this->storage[$banknote] += $quantity;
                $addSum += $banknote * $quantity;
            }
        }
        $this->storageSum += $addSum;
        return $addSum;
    }
}
/*
$initBanknotes = array(
    10 => 1000,
    20 => 1000,
    50 => 1000,
    100 => 1000,
    200 => 1000,
    500 => 1000,
    1000 => 500
);

$stor = new Storage($initBanknotes);

$sum = (int)readline("sum=");
var_dump($sum);
while($sum > 0)    {
    $ret = $stor->getQuantityBanknote($sum);
    var_dump($ret);
    $sum = (int)readline("sum=");
}

*/

?>