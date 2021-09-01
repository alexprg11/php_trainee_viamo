<?php
require_once "db.class.php";
/**
* Logging operations
* Format: DateTime:Operation:User 
*/

class Logging extends DB{
    private string $name;
    private string $separator = "#";
    private string $admin_email = "alexander.datsenko@gmail.com";
    private int $atm_no = 1;

    public function __construct(string $name = 'log.txt', int $atm_no = 1) {
        $this->name = $name;
        $this->atm_no = $atm_no;
    }

    public function getAtmNo() {
        return $this->atm_no;
    }

    /**
     * Save row to log file
     * @param string $operation {}
     */
    public function save(string $operation, int $user_id, float $sum, string $params) {
        $log = $this->atm_no . $this->separator .
               date('Y-m-d H:i:s') . $this->separator . 
               $operation . $this->separator .
               $user_id . $this->separator .
               $sum . $this->separator .
               $params . PHP_EOL;
        try {
            $this->run("INSERT INTO `operations` 
                (`client_id`,`operations`,`dt`,`sum`,`external_card`) VALUES 
                (?, ?, now(), ?, ?)", 
                [$user_id, $operation, $sum, (int)$params]);
            file_put_contents(__DIR__ . '/'.$this->name, $log, FILE_APPEND);
        } catch (Exception $e) {
            mail(
                $this->admin_email,
                "Error saveing log from ATM N ".$this->atm_no, 
                $e->getMessage()
            );
        }
    }

}

?>