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

use PowerOn\Utility\Inflector;

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
     * @var \PDO
     */
    private $_service;
    /**
     * Modelo a utilizar
     * @var Model
     */
    private $_model;
    /**
     * Configuración de la herramienta
     * @var type 
     */
    private $_config = [];
    /**
     * Inicializa la configuración de la base de datos
     * @param \PDO $service_provider Servicio de base de datos
     * @param array $config Configuración de la herramienta:
     * <ul>
     *  <li><i>tables_namespace</i> : namespace de la ubicación de las tablas (Por defecto es <code>App\Model\Tables\\</code>)
     * </ul>
     */
    public function __construct(\PDO $service_provider = NULL, array $config = []) {
        $this->_service = $service_provider;
        $this->_model = new Model($this->_service);
        $this->_config = $config + [
            'tables_namespace' => 'App\Model\\'
        ];
    }
    
    /**
     * Devuelve una instancia de la tabla solicitada
     * @param string $table_request Nombre de la tabla
     * @throws DataBaseServiceException
     * @return Table
     */
    public function get($table_request) {
        if ( !key_exists($table_request, $this->_table_registry) ) {
            $table_class = ($this->_config['tables_namespace'] ? $this->_config['tables_namespace'] : 'App\Model\\') 
                . 'Tables\\' . Inflector::classify($table_request);
            
            if ( !class_exists($table_class) ) {
                throw new DataBaseServiceException(sprintf('No existe la tabla (%s) con la clase (%s)', $table_request, $table_class));
            }
            
            /* @var $table Table */
            $table = new $table_class( $this->_model );
            $table->initialize();
            $this->_table_registry[$table_request] = $table;
        }
        
        return $this->_table_registry[$table_request];
    }
    
    /**
     * Devuelve el modelo de la base de datos
     * @return Model
     */
    public function model() {
        return $this->_model;
    }
    
    /**
     * Devuelve el servicio PDO de la base de datos
     * @return \PDO
     */
    public function service() {
        return $this->_service;
    }
}
