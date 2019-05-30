<?php
/** Author: Rami Azzazi, @ramiazzazi on github.
 * This is a fork from Programster's implementation of PHP's SessionHandler implementing the abstract SessionHandler
 *  extending the SessionHandlerInterface
 * The fork modifies the class to utilize PHP DATA OBJECTS (PDO) abstraction layer for accessing databases rather than mysqli
 * In addition to converting the code from mysqli to PDO, this release includes a fixes for the following bugs in the original code:
 * 1-Read function returning a null when rows on empty resulting in immediate session closure
 * 2-Garbage collection function including inconsistent datatypes in the SQL statement.
 * 3-Fixed close() to no longer return null as PHP relies on the interface function returning only true and false
 * 4-Part of the open function now is to set the db value back to PDO (from being set to null in close()
 **/

namespace FitnessFormulary\SessionHandler;
use PDO;

final class SessionHandler implements \SessionHandlerInterface
{   private $pdoConnection;
    private $dbConnection;
    private $dbTable;
    private $m_maxAge;


    /**
     * Create the session handler.
     * @param \mysqli $mysqli - the database connection to store sessions in.
     * @param string $tableName - the table within the database to store session data in.
     * @param int $maxAge - the maximum age in seconds of a session variable.
     */
    public function __construct(PDO $pdo, string $tableName, int $maxAge=86400)
    {
        $this->pdoConnection = $pdo;
        $this->dbConnection = $pdo;
        $this->dbTable = $tableName;
        $this->m_maxAge = $maxAge;

        $createSessionsTableQuery =
            "CREATE TABLE IF NOT EXISTS {$this->dbTable} (
                `id` varchar(32) NOT NULL,
                `modified_timestamp` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `data` mediumtext,
                PRIMARY KEY (`id`),
                KEY `modified_timestamp` (`modified_timestamp`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

        $stmt = $this->dbConnection->prepare($createSessionsTableQuery);
        $stmt->execute();
    }


    public function open($savePath, $sessionName)
    {
        $this->dbConnection = $this->pdoConnection;
        $sql =
            "DELETE FROM {$this->dbTable} 
             WHERE `modified_timestamp` < (NOW() - INTERVAL :m_maxAge SECOND)";
        $stmt = $this->dbConnection->prepare($sql);

        return $stmt->execute([':m_maxAge' => $this->m_maxAge]);
    }


    public function close()
    {
        $this->dbConnection = null;
        return true;
    }


    public function read($id)
    {
        $sql =
            "SELECT `data`  
             FROM `" . $this->dbTable . "` 
             WHERE `id` = :id";

        $stmt = $this->dbConnection->prepare($sql);
        $result = $stmt->execute([":id" => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);


        if ($result == false || $row == false)
        {
            $result = "";
        }
        else
        {
            $result = $row['data'];
        }

        return $result;
    }


    public function write($id, $data)
    {
        $sql = "REPLACE INTO {$this->dbTable} (id, data) 
                VALUES(:id, :data)";
        $stmt = $this->dbConnection->prepare($sql);
        return $stmt->execute([':id' => $id, ':data' => $data]);
    }


    public function destroy($id)
    {
        $sql = "DELETE FROM " . $this->dbTable . " WHERE id =:id";
        $stmt = $this->dbConnection->prepare($sql);
        $y = $stmt->execute([':id' => $id]);
        return $y;
    }


    public function gc($maxlifetime)
    {
        $sql =     "DELETE FROM {$this->dbTable} 
                    WHERE `modified_timestamp` < (NOW() - INTERVAL :m_maxAge SECOND)";;
        $stmt = $this->dbConnection->prepare($sql);
        return $stmt->execute([':m_maxAge' => $maxlifetime]);
    }
}

