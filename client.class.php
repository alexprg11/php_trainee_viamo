<?php

require_once "db.class.php";
require_once "storage.class.php";
require_once "log.class.php";

/**
 * Class for bank clients
 * Autorization by card and PIN-code, check brute force (block card)
 * 
 */
class Client extends DB{
    private int $user_id;
    private int $card_no;

    /** Storage of banknotes */
    private $storage = NULL;
    static  $max_tries = 5;
    private $tries;
    private $blocked_cards = array();
    /**
     * Logging
     */
    private $logging = NULL;

    public function __construct(Storage $stor, Logging $log){
        $this->storage = $stor;
        $this->logging = $log;
        $this->logOff(false);
    }

    /**
     * Is card_no exist?
     * @param int $card_no
     * @return bool - true if OK
     */
    public function checkCard(int $card_no) {
        $data = $this->getRow("SELECT count(*) as cnt FROM `clients` WHERE `card`= ? ", [$card_no]);
        if ($data['cnt']) return true;
        return false;
    }

    /**
     * Get card from client
     * @param int $card_no
     * @return bool - true if card checked
     */
    public function getCard(int $card_no) {
        if ($this->card_no > 0) {
            echo "The card is already loaded!" . PHP_EOL;
            return false;
        }
        if (!$this->checkCard($card_no)) {
            echo "Uncnown card!" . PHP_EOL;
            return false;
        }
        
        $this->card_no = $card_no;
        $this->tries = 0;
        return true;
    }

    /**
     * Autorization user by PIN-code
     * @param int pipn
     * @return bool - true if success
    */
    public function getPin(int $pin) {
        if ($this->card_no == 0) {
            echo "Card not loaded!" . PHP_EOL;
            return false;
        }

        $data = $this->getRow("SELECT `id` FROM `clients` WHERE `card`= ? AND `pin`= ?", [$this->card_no, $pin]);
        if($data) {
            $this->user_id = $data['id'];
            return true;
        }
        $this->tries++;
        if ($this->tries < self::$max_tries) {
            echo $this->tries . " Failed authorization attempt. After ".self::$max_tries. "th attempt the card will be blocked!" . PHP_EOL;
            return false;
        } else {
            $atm = $this->logging->getAtmNo();
            $ret = $this->run("update `clients` SET `atm_block`=? WHERE `card`= ?", [$atm, $this->card_no]);
            $this->blocked_cards[] = $this->card_no;
            $this->logOff(false);
            echo "Card is blocked due to password brute force". PHP_EOL;
            return false;
        }
        return false;
    }

    /**
     * Get current user balance
     * @return float - balance or false if user not autorized
     */
    public function getBalanceByCard() {
        if ($this->user_id) {
            $data = $this->getRow("SELECT `sum` FROM `clients` WHERE `id`= ?", [$this->user_id]);
            if ($data) {
                return (float)$data['sum'];
            }
        }
        return false;
    }

    /**
     * User ask money sum,
     * check this sum by card balance if sum over balance - reject,
     * check by user limit for getting money - if user overlimit - reject,
     * check by notes in current bank - if it not correct, ask to change sum
     * @param int $sum
     * @return array of banknotes or false
     */
    public function getMoney(int $sum) {
        try {
            $balance = $this->getBalanceByCard();
        } catch (Exception $e) {
            echo "Error getting the balance on the card - " . $e->getMessage() . PHP_EOL;
            return false;
        }
        
        if ($sum > $balance) {
            echo "Not enough money on the card" . PHP_EOL;
            return false;
        }
        try {
            $banknotes = $this->storage->getSum($sum);
        } catch (Exception $e) {
            $banknotes = array();
            echo "Error getting banknotes from storage - " . $e->getMessage() . PHP_EOL;
            return false;
        }

        if ($banknotes) {
            $ret = $this->run("UPDATE `clients` SET `sum`=`sum`-? WHERE `id`= ?", [$sum, $this->user_id]);
            $this->logging->save('get', $this->user_id, $sum, '');
            echo "Take the money:" . $sum . PHP_EOL;
            foreach ($banknotes as $banknote => $cnt) {
                for ($i=1; $i <= $cnt; $i++) echo $banknote . PHP_EOL;
            }
            return $banknotes;
        }
        return false;
    }

    /**
     * Getting banknotes from client
     * @param array $banknotes - mixed banknotes
     * @return bool - true if success
     */
    public function putMoney(array $banknotes) {
        try {
            $added = $this->storage->putSum($banknotes);
            $ret = $this->run("update `clients` SET `sum`=`sum`+? WHERE `id`= ?", [$added, $this->user_id]);
        } catch (Exception $e) {
            echo "Ошибка сохранения данных ".$e->getMessage() . PHP_EOL;
            return false;
        }
        $this->logging->save('put', $this->user_id, $added, '');
        echo "Accepted amount: ". $added . PHP_EOL;
        return true;
    }

    /**
     * Transfer money from client card to anothe card
     * @param float $sum
     * @param int $card - for receive money
     * @return bool - true when success
     */
    public function transfer(float $sum, int $card) {
        try {
            $balance = $this->getBalanceByCard();
            if ($balance >= $sum) {
                $ret = $this->run("update `clients` SET `sum`=`sum`-? WHERE `id`= ?", [$sum, $this->user_id]);
                $ret = $this->run("update `clients` SET `sum`=`sum`+? WHERE `card`= ?", [$sum, $card]);
                $this->logging->save('transfer', $this->user_id, $sum, $card);
                echo "Transfer success!" . PHP_EOL;
                return true;
            } else {
                echo "Not enough money for the operation" . PHP_EOL;
                return false;
            }
        } catch (Exception $e) {
            echo "Transfer error " . $e->getMessage();
        }
        return false;
    }

    /**
     * Return card to client
     * @param bool $message - show message with normal client exit
     */
    public function logOff(bool $message = true) {
        $this->user_id = 0;
        $this->card_no = 0;
        $this->tries  = 0;
        if ($message) echo "Don't forget you card!" . PHP_EOL;
    }

    /**
     * Free transfer to card 
     * @param array $banknotes 
     * @param int $card_no
     */
    public function freeTransfer(array $banknotes, int $card_no) {
        if (!$this->checkCard($card_no)) {
            echo "Unknown card!";
            return false;
        }
        $added = $this->storage->putSum($banknotes);
        $ret = $this->run("UPDATE `clients` SET `sum`=`sum`+? WHERE `card`= ?", [$added, $card_no]);
        $this->logging->save('transfer', 0, $added, $card_no);
        echo "Free Transfer {$added} - Success. The hand of the giver shall not fail!";
        return true;
    }
}

?>