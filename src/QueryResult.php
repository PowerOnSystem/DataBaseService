<?php

/*
 * Copyright (C) Makuc Julian & Makuc Diego S.H.
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
use \PDO;
use \PDOStatement;
/**
 * Query
 * @author Lucas Sosa
 * @version 0.1
 * @copyright (c) 2016, Lucas Sosa
 */
class QueryResult implements \Iterator {
    
    /**
     * Resultados en array asociativo
     * @var array
     */
    private $_results = [];
    /**
     * Resultado de la consulta PDO
     * @var PDOStatement
     */
    private $_pdo_statement = NULL;
    /**
     * Especifica si es un resultado único
     * @var boolean
     */
    private $_unique = FALSE;
    /**
     * Crea un nuevo resultado de consulta select
     * @param PDOStatement $pdo La respuesta PDO
     * @param boolean $unique Especifica si se trata de un resultado único
     */
    public function __construct(PDOStatement $pdo, $unique = FALSE) {
        $this->_pdo_statement = $pdo;
        $this->_unique = $unique;
    }
    /**
     * Iterator current
     * @return array
     */
    public function current() {
        return current($this->_results);
    }
    /**
     * Iterator key
     * @return mix
     */
    public function key() {
        return key($this->_results);
    }
    /**
     * Iterator next
     * @return mix
     */
    public function next() {
        return next($this->_results);
    }
    /**
     * Iterator rewind
     */
    public function rewind() {
        if ( !$this->_results ) {
            $this->toArray();
        }
        reset($this->_results);
    }
    /**
     * Iterator valid
     * @return boolean
     */
    public function valid() {
        return current($this->_results) ? TRUE : FALSE;
    }
    
    /**
     * Pasa todos los resultados a un array para iterar
     * @return array
     */
    public function toArray() {
        $this->_results = $this->_unique ? $this->_pdo_statement->fetch(\PDO::FETCH_ASSOC) : $this->_pdo_statement->fetchAll(PDO::FETCH_ASSOC);
        return $this->_results;
    }
    
    /**
     * Devuelve un array con los datos de la columna especificada
     * @param string $field nombre de la columna
     * @return array
     */
    public function column($field) {
        $column = [];
        while( $result = $this->_pdo_statement->fetch(PDO::FETCH_ASSOC) ) {
            if ( !key_exists($field, $result) ) {
                throw new DataBaseServiceException(sprintf('No existe la columna (%s) a obtener', $field), ['result' => $result]);
            }
            $column[] = $result[$field];
        }
        return $column;
    }
    
    /**
     * Devuelve un array con los datos combinados de un campo usando un campo para la clave y otro para el valor,
     * @param string|array $field_value Nombre del campo para el valor, puede ser un array con varios nombres que se
     * concatenarán con un espacio en blanco Ejemplo: $field_value = ['nombre', 'apellido']; $field_key = 'id'; 
     * <i>Resultado: ['1' => 'Esteban Moreira', '2' => 'Nicolás García', 3 => ...]</i>
     * @param string $field_key Nombre del campo para la clave, por defecto es id
     * @return array
     */
    public function combine($field_value, $field_key = 'id') {
        $column = [];
        while ($result = $this->_pdo_statement->fetch(PDO::FETCH_ASSOC) ) {
            $value = NULL;
            if ( is_array($field_value) ) {
                $values_array = [];
                foreach ($field_value as $f) {
                    if ( key_exists($f, $result) ) {
                        array_push($values_array, $result[$f]);
                    }
                }
                $value = implode(' ', $values_array);
            } else {
                $value = key_exists($field_value, $result) ? $result[$field_value] : NULL;
            }
            
            $column[key_exists($field_key, $result) ? $result[$field_key] : count($column)] = $value;
        }

        return $column;
    }
    
    /**
     * Devuelve todos los resultadaos utilizando el campo ID como indice
     * @return array
     */
    public function byID() {
        $results = [];
        while ( $data = $this->_pdo_statement->fetch(PDO::FETCH_ASSOC) ) {
            $results[key_exists('id', $data) ? $data['id'] : count($results)] = $data;
        }
        return $results;
    }
    
    /**
     * Devuelve la cantidad de resultados
     * @return type
     */
    public function count() {
        return (int)$this->_pdo_statement->rowCount();
    }
}
