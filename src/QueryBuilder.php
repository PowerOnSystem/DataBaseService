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

/**
 * Query es un objeto retornado por una consulta en una tabla,
 * controla los resultados devueltos agregando métodos útiles para procesarlos.
 * @author Lucas Sosa
 * @version 0.1
 */
class QueryBuilder {
    /**
     * Nombre de la tabla principal
     * @var string
     */
    private $_table = NULL;
    /**
     * Tipo de acción realizada
     * [insert, update, select, delete]
     * @var string
     */
    private $_type = NULL;
    /**
     * Array con las tablas de la consulta
     * @var array
     */
    private $_tables = [];
    /**
     * Campos a seleccionar de la consulta
     * @var array
     */
    private $_fields = [];
    /**
     * Condiciones de la consulta
     * @var array
     */
    private $_conditions = [];
    /**
     * Campos de la condición
     * @var array
     */
    private $_condition_fields = [];
    /**
     * Tablas a incluir en la consulta
     * @var array
     */
    private $_joins = [];
    /**
     * Limit de consulta [inicio, limite] o [limite]
     * @var array
     */
    private $_limit = [];
    /**
     * Valores a procesar en consulta
     * @var array
     */
    private $_values = [];
    /**
     * Order de la consulta
     * @var array
     */
    private $_order = [];
    /**
     * Consulta generada
     * @var string 
     */
    private $_query = NULL;    
    
    const SELECT_QUERY = 'select';
    const UPDATE_QUERY = 'update';
    const DELETE_QUERY = 'delete';
    const INSERT_QUERY = 'insert';
       
    const OPERATOR_TYPES = ['LIKE', '=', '!=', '<', '>', '<=', '>=', 'NOT', 'NOT LIKE', 'JSON', 'REGEXP'];
    const CONDITIONAL_TYPES = ['AND', 'OR', 'AND NOT', 'OR NOT', 'NOT'];
    
    /**
     * Crea una nueva consulta
     * @param string $type Tipo de consulta
     */
    public function __construct($type) {
        $this->_type = $type;
    }
    
    /**
     * Establece la tabla a trabajar en la consulta
     * @param string $table
     */
    public function table($table) {
        $this->_table = $table;
    }
    
    /**
     * Agrega campos a una consulta de tipo <b>select</b>, <b>update</b> o <b>insert</b>
     * @param array|string $fields Campos a agregar
     * @throws DataBaseServiceException
     */
    public function fields($fields) {
        if ($this->_type == self::DELETE_QUERY) {
            throw new DataBaseServiceException(sprintf('Esta funci&oacute;n no admite el tipo de consulta (%s)', self::DELETE_QUERY));
        }
        $field = is_array($fields) ? $fields : ($fields != '*' && $fields != 'all' ? [$fields] : ['*']);
        $this->_fields = $field + $this->_fields;
    }
    
    /**
     * Agrega valores a una consulta de tipo <b>update</b> o <b>instert</b>
     * @param array $values Valores a agregar
     * @throws DataBaseServiceException
     */
    public function values(array $values) {
        if ($this->_type == self::SELECT_QUERY || $this->_type == self::DELETE_QUERY) {
            throw new DataBaseServiceException(sprintf('Esta funci&oacute;n no admite el tipo de consulta (%s) ni (%s)',
                    self::SELECT_QUERY, self::DELETE_QUERY));
        }
        $this->_values += $values;
    }
    
    /**
     * Agrega condiciones a la consulta
     * @param array $conditions Condiciones
     */
    public function conditions(array $conditions) {
        $this->_conditions += $conditions;
    }
    
    /**
     * Ordena los resultados de una consulta de tipo <b>select</b>
     * @param array $order Array estableciendo el orden
     */
    public function order(array $order) {
        $this->_order += $order;
    }
    
    /**
     * Limita los resultados de una consulta
     * @param array $limit Array con los limites de la tabla [inicio, limite] o [limite]
     */
    public function limit( array $limit ) {
        $this->_limit = $limit;
    }
        
    /**
     * Asocia tablas a una consutla de tipo <b>select</b>
     * @param array $joins Array con las asociaciones
     * @throws DataBaseServiceException
     */
    public function join(array $joins) {
        if ( $this->_type != self::SELECT_QUERY ) {
            throw new DataBaseServiceException(sprintf('Este m&eacute;todo es exclusivo de la acci&oacute;n (%s)', self::SELECT_QUERY));
        }
        
        $this->_joins += $joins;
        $this->_tables += array_keys($joins);
    }
    
    /**
     * Devuelve la consulta construida a partir de los parámetros solicitados
     * @return string
     */
    public function getQuery() {
        switch ($this->_type) {
            case self::INSERT_QUERY: $this->_query = $this->buildInsertQuery(); break;
            case self::UPDATE_QUERY: $this->_query = $this->buildUpdateQuery(); break;
            case self::DELETE_QUERY: $this->_query = $this->buildDeleteQuery(); break;
            case self::SELECT_QUERY: $this->_query = $this->buildSelectQuery(); break;
        }

        return $this->_query;
    }
    /**
     * Devuelve los valores de la consulta <b>update</b> o <b>insert</b>
     * @return array
     */
    public function getValues() {
        return $this->_values;
    }
    
    /**
     * Devuelve los parámetros a pasar a PDO
     * @return array
     */
    public function getParams() {
        return ($this->_values + $this->_condition_fields);
    }
    
    /**
     * Devuelve el tipo de consulta a realizar
     * @return string
     */
    public function getType() {
        return $this->_type;
    }
    
    public function debug() {
        return ['query' => $this->_query, 'params' => $this->getParams()];
    }
    
    /**
     * Configura una consulta de tipo SELECT
     * @return string
     */
    private function buildSelectQuery() {
        return 'SELECT ' 
            . ($this->_fields ? $this->processFields() : '*') 
            . ' FROM ' . $this->_table
            . ($this->_joins ? $this->processJoin() : NULL)
            . ($this->_conditions ? $this->processCondition() : NULL )
            . ($this->_order ? $this->processOrder() : NULL)
            . ($this->_limit ? $this->processLimit() : NULL);
        
        
    }
    
    /**
     * Configura una consulta de tipo UPDATE
     * @return integer Devuelve el número de filas afectadas
     */
    private function buildUpdateQuery() {
        return 'UPDATE ' . $this->_table . ' SET ' 
            . $this->processUpdateValues() 
            . ($this->_conditions ? $this->processCondition() : NULL );
    }
    
    /**
     * Configura una consulta de tipo INSERT
     * @return integer Devuelve el ID de la fila insertada
     */
    private function buildInsertQuery() {
        return 'INSERT INTO ' . $this->_table . ' (' 
            . $this->processFields() . ') VALUES (' 
            . $this->processInsertValues() . ')'
        ;
    }
    
    /**
     * Configura una consulta de tipo DELETE
     * @return integer Devuelve el número de filas eliminadas
     */
    private function buildDeleteQuery() {
        if ( empty($this->_conditions) ) {
            throw new DataBaseServiceException('Atenci&oacute;n, esta funci&oacute;n eliminar&aacute; todo el contenido de la tabla, '
                    . 'esta acci&oacute;n debe realizarse a trav&eacute;s del administrador '
                    . 'de su base de datos con la funci&oacute;n TRUNCATE');
        }
        return 'DELETE FROM ' . $this->_table . $this->processCondition();
    }
    
    /**
     * Verifica si hay una función
     * @param string $subject La cadena a verificar la función
     * @return boolean
     */
    private function checkFunction( $subject ) {
        if ( preg_match('/^FNC[a-zA-Z0-9]+\(.+\)FNC$/', $subject) ) {
            return substr($subject, 3, -3);
        }
        
        return FALSE;
    }


    /**
     * Procesa los valores a insertar o actualizar de la consulta
     * @return type
     */
    private function processInsertValues() {
        $new_values = array_map(function($key) {
            return ':' . $key;
        }, array_keys($this->_values));
        
        return implode(', ', $new_values);
    }
    
    /**
     * Procesa los valores a insertar o actualizar de la consulta
     * @return type
     */
    private function processUpdateValues() {
        $changes = array_map(function($key){
            return ' `' . $key . '` = :' . $key;
        }, array_keys($this->_values));
        
        return implode(', ', $changes);
    }
    
    /**
     * Procesa los campos de la consulta select o update
     * @return string
     */
    private function processFields() {
        $new_fields = [];
        foreach ( $this->_fields as $table => $field ) {
            if ($field == '*' && count($this->_fields) == 1 && $this->_tables) {
                $new_fields[] = '`' . $this->_table . '`.*';
                foreach ($this->_tables as $joined_table) {
                    $new_fields[] = '`' . $joined_table . '`.*';
                }
            } else if ( is_string($field) && $function = $this->checkFunction($field) ) {
                $new_fields[] = $function;
            } else {
                if ( is_array($field) ) {
                    $new_sub_fields = [];
                    foreach ($field as $mask => $sub_field) {
                        $new_sub_fields[] = '`' . $table . '`.`' . $sub_field . '` ' . ( !is_numeric($mask) ? ' AS `' . $mask . '`' : '');
                    }
                    $new_fields[] = implode(',', $new_sub_fields);
                } else {
                    $new_fields[] = $field == '*' && !in_array($table, $this->_tables) 
                        ? '`' . $this->_table . '`.*' : (
                            (is_string($table) && in_array($table, $this->_tables) ? '`' . $table . '`.' : '') 
                            . ($field == '*' ? '*' : '`' . $field . '`' . ( !is_numeric($table) ? ' AS `' . $table . '`' : '') )
                        );
                }
            }
        }
        
        return implode(',', $new_fields);
    }
    
    
    /**
     * Procesa el orden de los resultados
     * @return string
     */
    private function processOrder() {
        $sorts = [];
        foreach ( $this->_order as $sort_mode => $sort_by ) {
            if ( !is_array($sort_by) ) {
                $sort_by = [$sort_by];
            }
            foreach ($sort_by as $field) {
                $sorts[] = '`' . $field . '` ' . (strtoupper($sort_mode) == 'DESC' ? 'DESC' : 'ASC');
            }
        }
        return ' ORDER BY ' . implode(', ', $sorts);
    }
        
    /**
     * Procesa las condiciones
     * @return string
     */
    private function processCondition() {
        return ' WHERE ' . $this->parseCondition($this->_conditions, $this->_joins ? $this->_table : NULL);
    }
    
    /**
     * Procesa las asociaciones de tablas
     * @return string
     */
    private function processJoin() {
        $joins = '';

        foreach ($this->_joins as $table => $value) {
            $config = [
                'table' => NULL,
                'type' => 'LEFT',
                'conditions' => NULL
            ] + $value;
            if (!array_intersect(array_keys($value), ['table', 'type', 'conditions'])) {
                $config['conditions'] = $value;
            }
            $joins .= ' ' . $config['type'] . ' JOIN ' 
                    . '`' . ($config['table'] ?: $table) . '` ' 
                    . ($config['table'] ? $table : '') . ' ON ' 
                    . $this->parseCondition($config['conditions'], NULL, 'AND', FALSE, FALSE);
        }
        
        return $joins;
    }
    
    /**
     * Procesa el limit de la consulta
     * @throws DataBaseServiceException
     * @return string
     */
    private function processLimit() {
        if ( empty($this->_limit) ) {
            return FALSE;
        }
        
        if ( !key_exists(0, $this->_limit) ) {
            throw new DataBaseServiceException('El limit de la consulta esta mal configurado, '
                    . 'asegurese que sea un array simple [start, limit] o [limit]', ['limit' => $this->_limit]);
        }
        
        return ' LIMIT ' . (int)$this->_limit[0] . ($this->_limit[1] ? ', ' . (int)$this->_limit[1] : '');
    }
    
    /**
     * Procesa una lista de condiciones en array
     * @param array $conditions Las condiciones
     * @param string $table Nombre de la tabla inicial
     * @param string $initialOperator Operador Inicial
     * @param string $forceKey Clave forzada
     * @return string La consulta procesada compelta
     */
    private function parseCondition(array $conditions, $table = NULL, $initialOperator = NULL, $forceKey = NULL, $prepare = TRUE) {
        $cond = '';
        $op = '';
        foreach ($conditions as $key => $value) {
            if (!is_array($value) && !$value) {
                continue;
            }

            if ( is_string($value) && in_array($value, self::CONDITIONAL_TYPES) ) {
                $op = $value;
                continue;
            }
            
            $isTable = in_array($key, $this->_tables) ? $key : NULL;
            
            if ( is_array($value) ) {
                $cond .= ' ' . $op . ' (' . $this->parseCondition($value, $isTable, $isTable ? NULL : 'OR', $isTable ? NULL : $key) . ')';
            } else {
                $rfield = $forceKey ? $forceKey : $key;
                
                list ($operator, $field, $fieldTable) = $this->parseField($rfield);
                
                if ($prepare) {
                    $cond_field = 'cnd_' . ($fieldTable ? $fieldTable . '_'  : '') . $field . (is_numeric($key) ? $key : '');

                    $this->_condition_fields[$cond_field] = addslashes(trim($value));
                } else {
                    list(,$valueField, $valueFieldTable) = $this->parseField($value);
                }
                
                $cond .= ' ' . $op . ' ' . ($table ? '`' . $table . '`.' : '') 
                        . ($fieldTable ? '`' . $fieldTable . '`.' : '') 
                        . '`' . $field . '` ' 
                        . $operator . ' '
                        . ($prepare 
                            ? ':' . $cond_field 
                            : ($valueFieldTable ? '`' . $valueFieldTable . '`.' : '') . '`' . $valueField . '` ' 
                        );
            }
            $op = $op ?: ($initialOperator ? $initialOperator : 'AND');
        }

        return $cond;
    }
    /**
     * Devuelve la tabla el operador y el campo encontrado en el field
     * @param string $field
     * @return array
     */
    private function parseField($field) {
        $operator = '=';
        foreach (self::OPERATOR_TYPES as $find) {
            if (strpos($field, $find)) {
                $operator = $find;
                break;
            }
        }
        $findField = substr($field, 0, strpos($field, ' ') ?: strlen($field));
        $findTable = explode('.', $field);
        return [
            $operator, 
            count($findTable) > 1 ? $findTable[1] : $findField,
            key_exists(1, $findTable) ? $findTable[0] : NULL,
        ];
    }
}
