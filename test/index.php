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
        $service = new PDO(sprintf('mysql:host=%s;dbname=%s;port=%s', 'localhost', 'unitest', 3306), 'root', '***');
    } catch (PDOException $e) {
        throw new PowerOn\Database\DataBaseServiceException($e->getMessage(), ['pdo' => $e]);
    }
    //Creamos el controlador de la base de datos
    $database = new PowerOn\Database\Model( $service, [
        'containReferenceSuffix' => 'Id',
    ] );
    $times['creacion database'] = number_format((microtime(TRUE) - $time), 4) . 'ms';

    $typology = $database
      ->select()
      ->from('typologies')
      ->where(['typologies.id' => 10])
      ->contain([
        'belongsTo' => [
            'works' => [
                'belongsTo' => ['users']
            ]
        ],
        'hasMany' => [
            'items' => [
                'belongsTo' => [
                    'products', 'processes'
                ],
                'hasMany' => [
                    'item_panels' => [
                        'hasMany' => [
                            'item_glass_calcules' => [
                                'belongsTo' => 'glasses'
                            ]
                        ]
                    ]
                ]
            ]
        ]
      ])
      
      ->first()
    ;

    $times['consultas finalizadas'] = number_format((microtime(TRUE) - $time), 4) . 'ms';
    $times['tiempo de consultas'] = number_format($times['consultas finalizadas'] - $times['creacion database'], 4) . 'ms';
    !d($typology);
    !d($database->debug(PowerOn\Database\Model::DEBUG_QUERIES));
    $newTime = microtime(TRUE);
    $times['tiempo de consulta simple'] = number_format((microtime(TRUE) - $newTime), 4) . 'ms';
} catch (\PowerOn\Database\DataBaseServiceException $e) {
    d($e);
    echo '<h1>' . $e->getMessage() . '</h1>';
    !d($e->getContext());
}

var_dump($times);