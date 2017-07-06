<?php

/*
 * Copyright (C) PowerOn Sistemas
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace PowerOn\Database;
use PowerOn\Exceptions\DevException;
use PowerOn\Utility\Inflector;
use PowerOn\Utility\Config;
/**
 * Database
 * @author Lucas Sosa
 * @version 0.1
 */
class Database {
    /**
     * Registro de tablas agregadas
     * @var array
     */
    private $_table_registry = [];
    /**
     * Servcio de base de datos, por defecto MySQLi
     * @var \mysqli
     */
    private $_service;
    /**
     * Modelo a utilizar
     * @var Model
     */
    private $_model;
    /**
     * Inicializa la configuración de la base de datos
     * @throws DevException
     */
    public function __construct() {
        $host = Config::get('DataBaseService.host');
        $user = Config::get('DataBaseService.user');
        $password = Config::get('DataBaseService.password');
        $database = Config::get('DataBaseService.database');
        $port = Config::get('DataBaseService.port');
        
        if ( !$host ) {
            throw new DevException('No se configur&oacute; la base de datos correctamente, '
                    . 'verifique la configuraci&oacute;n de la aplicaci&oacute;n.');
        }
        
        $this->_service = @new \mysqli($host, $user, $password, $database, $port);
        
        if ( $this->_service->connect_errno ) {
            throw new DevException('Error al conectar la base de datos', [
                    'mysql_code' => $this->_service->connect_errno, 
                    'mysql_message'  => $this->_service->connect_error,
                    'mysql_host' => $host, 
                    'mysql_user' => $user, 
                    'mysql_password' => $password,
                    'mysql_database' => $database
                ]
            );
        }
        
        if ( !$this->_service->set_charset('utf8') ) {
            throw new DevException('Error al establecer la codificaci&oacute;n a UTF-8', [
                    'mysql_code' => $this->_service->errno, 
                    'mysql_message' => $this->_service->error
                ]
            );
        }
        
        $this->_model = new Model($this->_service);
    }
    
    /**
     * Devuelve una instancia de la tabla solicitada
     * @param string $table_request Nombre de la tabla
     * @throws DevException
     * @return Table
     */
    public function get($table_request) {
        if ( !key_exists($table_request, $this->_table_registry) ) {
            $table_class = 'App\Model\Tables\\' . Inflector::classify($table_request);
            
            if ( !class_exists($table_class) ) {
                throw new DevException(sprintf('No existe la tabla (%s) con la clase (%s)', $table_request, $table_class));
            }
            
            /* @var $table Table */
            $table = new $table_class( $this->_model );
            $table->initialize();
            $this->_table_registry[$table_request] = $table;
        }
        
        return $this->_table_registry[$table_request];
    }
}
