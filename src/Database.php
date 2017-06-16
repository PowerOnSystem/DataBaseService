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

/**
 * Database
 * @author Lucas Sosa
 * @version 0.1
 */
class Database extends \mysqli {
    
    private $_table_registry = [];
    
    /**
     * Inicializa la configuración de la base de datos
     * @param string $host Servidor de la base de datos
     * @param string $user Nombre de usuario
     * @param string $password Contraseña
     * @param string $database Base de datos a utilizar
     * @throws DevException
     */
    public function __construct( $host, $user, $password, $database ) {
        parent::__construct($host, $user, $password, $database);
        
        if ( $this->connect_errno ) {
            throw new DevException('Error al conectar la base de datos', [
                    'mysql_code' => $this->connect_errno, 
                    'mysql_message'  => $this->connect_error,
                    'mysql_host' => CNC_DB_HOST, 
                    'mysql_user' => CNC_DB_USER, 
                    'mysql_password' => CNC_DB_PSS,
                    'mysql_database' => $database
                ]
            );
        }
        if ( !$this->set_charset('utf8') ) {
            throw new DevException('Error al establecer la codificaci&oacute;n a UTF-8', [
                    'mysql_code' => $this->errno, 
                    'mysql_message' => $this->error
                ]
            );
        }
    }
    
    /**
     * Devuelve una instancia de la tabla solicitada
     * @param string $table Nombre de la tabla
     * @throws DevException
     * @return Table
     */
    public function get($table) {
        if ( !key_exists($table, $this->_table_registry) ) {
            $table_class = 'App\Model\Tables\\' . Inflector::classify($table);
            if ( !class_exists($table_class) ) {
                throw new DevException(sprintf('No existe la tabla (%s) con la clase (%s)', $table, $table_class));
            }
            
            $this->_table_registry[$table] = new $table_class;
        }
        
        return $this->_table_registry[$table];
    }
}
