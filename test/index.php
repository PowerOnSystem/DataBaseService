<?php
/*
 * Archivo de testeo del framework
 */
session_start();

define('ROOT', dirname(dirname(__FILE__)));
define('DS', DIRECTORY_SEPARATOR);

require ROOT . DS . 'vendor' . DS . 'autoload.php';
$time = microtime(TRUE);
$times = [];
try {
    try {
        //Creamos un servicio PDO para la base de datos MySQL con la clase que viene en la libreria
        $service = new PDO(sprintf('mysql:host=%s;dbname=%s;port=%s', 'localhost', 'cncdata', 3306), 'root', 'marcos6745');
    } catch (PDOException $e) {
        throw new PowerOn\Database\DataBaseServiceException($e->getMessage(), ['pdo' => $e]);
    }
    //Creamos el controlador de la base de datos
    $database = new PowerOn\Database\Model( $service );
    $times['creacion database'] = number_format((microtime(TRUE) - $time), 4) . 'ms';
    $users = $database
            ->select(['contacts' => ['id', 'company']])
            ->from('contacts')
            ->containMany([
                'persons' => [
                    'containMany' => ['phones', 'work_days']
                ],
                'phones'
            ])
            ->containOne('commercial_terms')
            ->all()->toArray();
    $times['consultas finalizadas'] = number_format((microtime(TRUE) - $time), 4) . 'ms';
    $times['tiempo de consultas'] = number_format($times['consultas finalizadas'] - $times['creacion database'], 4) . 'ms';
    //d($users);
    !d($database->debug(PowerOn\Database\Model::DEBUG_QUERIES));
    $newTime = microtime(TRUE);
    $q = $service->query('SELECT id, company FROM contacts');
    while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
        $contacts[$r['id']] = $r;
        //$p = $service->query('select name, id from persons where contact_id = ' . $r['id']);
        //$contacts[$r['id']]['persons'] = $p->fetchAll(PDO::FETCH_ASSOC);
        
    }
    $times['tiempo de consulta simple'] = number_format((microtime(TRUE) - $newTime), 4) . 'ms';
    
} catch (\PowerOn\Database\DataBaseServiceException $e) {
    echo '<h1>' . $e->getMessage() . '</h1>';
    !d($e->getContext());
}

var_dump($times);