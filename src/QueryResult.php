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
class QueryResult implements \Iterator, \ArrayAccess {
    
    /**
     * Resultados en array asociativo
     * @var array
     */
    private $results = [];
    /**
     * Resultado de la consulta PDO
     * @var PDOStatement
     */
    private $pdo_statement = NULL;
    /**
     * Especifica si es un resultado único
     * @var boolean
     */
    private $unique = FALSE;
    /**
     * Tablas incluidas con contain()
     * @var array
     */
    private $contains_one = [];
    /**
     * Resultados de inclusión múltiple
     * @var array
     */
    private $contains_many = [];
    
    /**
     * Crea un nuevo resultado de consulta select
     * @param \PDOStatement $pdo La respuesta PDO
     * @param boolean $unique Especifica si se trata de un resultado único
     */
    public function __construct(\PDOStatement $pdo, $unique = FALSE, array $containsOne = [], array $containsMany = []) {
        $this->pdo_statement = $pdo;
        $this->unique = $unique;
        $this->contains_one = $containsOne;
        $this->contains_many = $containsMany;
    }
    /**
     * Iterator current
     * @return array
     */
    public function current() {
        return current($this->results);
    }
    /**
     * Iterator key
     * @return mix
     */
    public function key() {
        return key($this->results);
    }
    /**
     * Iterator next
     * @return mix
     */
    public function next() {
        return next($this->results);
    }
    /**
     * Iterator rewind
     */
    public function rewind() {
        if ( !$this->results ) {
            $this->toArray();
        }
        reset($this->results);
    }
    /**
     * Iterator valid
     * @return boolean
     */
    public function valid() {
        return current($this->results) ? TRUE : FALSE;
    }
    
    /**
     * Pasa todos los resultados a un array para iterar
     * @return array
     */
    public function toArray() {
        if ($this->results) {
            return $this->results;
        }
        
        $this->results = $this->unique 
                ? $this->pdo_statement->fetch(\PDO::FETCH_ASSOC) 
                : $this->pdo_statement->fetchAll(PDO::FETCH_ASSOC);
        
        if ( $this->contains_one ) {
            $newResults = [];
            $allResults = $this->unique ? [$this->results] : $this->results;
            foreach ($allResults as $result) {
                $newFields = $result;
                foreach ($this->contains_one as $alias => $table) {
                    $containField = is_string($alias) ? $alias : $table;
                    foreach ($result as $field => $data) {
                        if ( strpos($field, '__contain_' . $alias . '__') !== FALSE ) {
                            $newFields[$containField][substr($field, strlen('__contain_' . $alias . '__'))] = $data;
                            unset($newFields[$field]);
                        }
                    }
                    if (key_exists($containField, $newFields) && is_null(reset($newFields[$containField])) ) {
                        $newFields[$containField] = NULL;
                    }
                }
                
                $newResults[] = $newFields;
            }
            
            $this->results = $this->unique ? reset($newResults) : $newResults;
            $this->contains_one = [];
        }

        return $this->results;
    }
    
    /**
     * Devuelve un array con los datos de la columna especificada
     * @param string $field nombre de la columna
     * @return array
     */
    public function column($field) {
        return $this->_join($field, $this->toArray());
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
        $results = $this->toArray();

        return $this->_combine($field_value, $field_key, $results);
    }
    /**
     * Devuelve todos los resultados utilizando el un campo específico como indice
     * @return array
     */
    public function by($field) {
        $results = $this->toArray();
        $columns = array_column($results, $field);
        if ( $results && !$columns ) {
            throw new \LogicException(sprintf('El campo clave "%s" no esta especificada en la tabla.', $field));
        }
        
        return array_combine($columns, $results);
    }
    
    /**
     * Devuelve todos los resultados utilizando el campo ID como indice
     * @return array
     */
    public function byID() {
        return $this->by('id');
    }
    
    /**
     * Devuelve los resultados agrupados por un campo
     * <pre>Ej: 
     * $data = [
     *  ['id' => 9, 'name' => 'test1', 'color' => 'green']
     *  ['id' => 2, 'name' => 'test2', 'color' => 'green']
     *  ['id' => 8, 'name' => 'test3', 'color' => 'red']
     *  ['id' => 24, 'name' => 'test4', 'color' => 'orange']
     *  ['id' => 34, 'name' => 'test4', 'color' => 'red'
     * ]
     * $re = $results->groupedBy('color'); 
     * var_dump($re);
     * (array) $re => [
     *   'green' => [
     *      ['id' => 9, ...]
     *      ['id' => 2, ...]
     *   ],
     *   'red' => [
     *      ['id' => 8, ...]
     *      ['id' => 34, ...]
     *   ],
     *   ...
     * ]
     * 
     * @param string $key Nombre del campo
     * @return array
     */
    public function groupedBy($key) {
        $results = $this->toArray();
        $merged = [];
        foreach ( $results as $result ) {
            $merged[$result[$key]][] = $result;
        }
        
        return $merged;
    }
    
    /**
     * Devuelve la cantidad de resultados
     * @return type
     */
    public function count() {
        return (int)$this->pdo_statement->rowCount();
    }
    
    /**
     * Modifica los resultados de la respuesta
     * @param array $results
     */
    public function setResults($results) {
        $this->results = $results;
        
        return $this->results;
    }

    public function injectContains(array $containsMany) {
        /* @var $containsMany QueryResult[] */
        foreach ($containsMany as $alias => $contain) {
            $containData = $this->contains_many[$alias];
            if ($this->unique) {
                $this->results[$alias] = $contain->toArray();
            } else {
                $newResults = [];
                $allContains = $contain->toArray();
                $allContainByKey = $contain->groupedBy($containData['key']);
                foreach ($this->results as $key => $result) {
                    $parentKeyValue = $result[$containData['parentKey']];
                    $newResults[$key] = $result;
                    
                    $contains = $this->setResults(
                        key_exists($parentKeyValue, $allContainByKey) ? $allContainByKey[$parentKeyValue] : []
                    );

                    if ($containData['combine'] && !$containData['column']) {
                        $contains = $contain->combine($containData['combine'][0], $containData['combine'][1]);
                    } else if ($containData['by']) {
                        $contains = $contain->by($containData['by']);
                    }
                    
                    if ($containData['column']) {
                        $contains = $contain->column($containData['column']);
                    }
                    
                    $newResults[$key][$alias] = $contains;
                    $contain->setResults($allContains);
                }
                
                $this->results = $newResults;
            }
        }
    }
    
    private function _combine($field_value, $field_key, array $results) {
        return array_combine(
                array_column($results, $field_key),
                $this->_join($field_value, $results)
            )
        ;
    }
    
    private function _join($field_value, array $results, $glue = ' ') {
        return !is_array($field_value) 
            ? array_column($results, $field_value)
            : array_map(
                function($result) use ($field_value, $glue) { 
                    return implode($glue, array_map(
                            function($field) use ($result) {
                                return $result[$field]; 
                            }, $field_value
                        )
                    );
                }, $results
            );
    }

    public function offsetSet($offset, $valor) {
        $this->toArray();
        if (is_null($offset)) {
            $this->results[] = $valor;
        } else {
            $this->results[$offset] = $valor;
        }
    }

    public function offsetExists($offset) {
        $this->toArray();
        return isset($this->results[$offset]);
    }

    public function offsetUnset($offset) {
        $this->toArray();
        unset($this->results[$offset]);
    }

    public function offsetGet($offset) {
        $this->toArray();
        
        return isset($this->results[$offset]) ? $this->results[$offset] : null;
    }

}
