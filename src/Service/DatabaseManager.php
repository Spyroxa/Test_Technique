<?php

namespace App\Service;
use Symfony\Component\Yaml\Yaml;
class DatabaseManager

{
    private $connection;
    
    public function __construct()
    {
        try {
            $yamlPath = __DIR__ . '/../../config/database.yaml';
            $config = Yaml::parseFile($yamlPath);

            if (!isset($config['parameters'])) {
                throw new \InvalidArgumentException('La clé "parameters" est absente dans le fichier YAML.');
            }

            $params = $config['parameters'];
            

            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;port=%s',
                $params['database_host'],
                $params['database_name'],
                $params['database_port']
            );
            $this->connection = new \PDO($dsn, $params['database_user'], $params['database_password']);
        } catch (\PDOException $e) {
            die("Erreur de connexion à la base de données : " . $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            die("Erreur dans la configuration YAML : " . $e->getMessage());
        }
    }

    public function executeQuery($query, $parameters = [])
    {
        try {
            $statement = $this->connection->prepare($query);
            $statement->execute($parameters);
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            die("Erreur d'exécution de la requête : " . $e->getMessage());
        }
    }
    public function execute($query, $parameters = [])
    {
        try {
            $statement = $this->connection->prepare($query);
            $statement->execute($parameters);

           
            return $this->connection->lastInsertId();
        } catch (\PDOException $e) {
            die("Erreur d'exécution de la requête : " . $e->getMessage());
        }
    }
    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }
}

