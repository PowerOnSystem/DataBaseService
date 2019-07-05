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
    private $table = NULL;
    /**
     * Alias de la tabla principal
     * @var string
     */
    private $tableAlias = NULL;
    /**
     * Tipo de acción realizada
     * [insert, update, select, delete]
     * @var string
     */
    private $type = NULL;
    /**
     * Array con las tablas de la consulta
     * @var array
     */
    private $tables = [];
    /**
     * Campos a seleccionar de la consulta
     * @var array
     */
    private $fields = [];
    /**
     * Condiciones de la consulta
     * @var array
     */
    private $conditions = [];
    /**
     * Campos de la condición
     * @var array
     */
    private $condition_fields = [];
    /**
     * Tablas a incluir en la consulta
     * @var array
     */
    private $joins = [];
    /**
     * Limit de consulta [inicio, limite] o [limite]
     * @var array
     */
    private $limit = [];
    /**
     * Valores a procesar en consulta
     * @var array
     */
    private $values = [];
    /**
     * Order de la consulta
     * @var array
     */
    private $order = [];
    /**
     * Consulta generada
     * @var string 
     */
    private $query = NULL;
    /**
     * Relacions configuradas en la consulta
     * @var array
     */
    private $contains = [];
    
    /**
     * Prepara para organizar resultados "column", "combine" y "by"
     * @var array
     */
    private $prepare = [];
    
    const SELECT_QUERY = 'select';
    const UPDATE_QUERY = 'update';
    const DELETE_QUERY = 'delete';
    const INSERT_QUERY = 'insert';
       
    const OPERATOR_TYPES = ['LIKE', '=', '!=', '<', '>', '<=', '>=', 'IS NOT', 'NOT', 'NOT LIKE', 'JSON', 'REGEXP', 'IS', 'IN'];
    const CONDITIONAL_TYPES = ['AND', 'OR', 'AND NOT', 'OR NOT', 'NOT'];
    
    /**
     * Crea una nueva consulta
     * @param string $type Tipo de consulta
     */
    public function __construct($type = NULL) {
        $this->type = $type;
    }
    
    /**
     * Establece la tabla a trabajar en la consulta
     * @param string $table
     */
    public function table($table) {
        $this->table = is_array($table) ? reset($table) : $table;
        $this->tableAlias = is_array($table) ? key($table) : NULL;
    }
    
    /**
     * Agrega campos a una consulta de tipo <b>select</b>, <b>update</b> o <b>insert</b>
     * @param array|string $fields Campos a agregar
     * @throws DataBaseServiceException
     */
    public function fields($fields) {
        if ($this->type == self::DELETE_QUERY) {
            throw new DataBaseServiceException(sprintf('Esta funci&oacute;n no admite el tipo de consulta (%s)', self::DELETE_QUERY));
        }
        $field = is_array($fields) ? $fields : ($fields != '*' && $fields != 'all' ? [$fields] : ['*']);
        $this->fields = $field + $this->fields;
    }
    
    /**
     * Agrega valores a una consulta de tipo <b>update</b> o <b>instert</b>
     * @param array $values Valores a agregar
     * @throws DataBaseServiceException
     */
    public function values(array $values) {
        if ($this->type == self::SELECT_QUERY || $this->type == self::DELETE_QUERY) {
            throw new DataBaseServiceException(sprintf('Esta funci&oacute;n no admite el tipo de consulta (%s) ni (%s)',
                    self::SELECT_QUERY, self::DELETE_QUERY));
        }
        $this->values += $values;
    }
    
    /**
     * Agrega condiciones a la consulta
     * @param array $conditions Condiciones
     */
    public function conditions(array $conditions) {
        $this->conditions += $conditions;
    }
    
    /**
     * Ordena los resultados de una consulta de tipo <b>select</b>
     * @param array $order Array estableciendo el orden
     */
    public function order(array $order) {
        $this->order += $order;
    }
    
    /**
     * Limita los resultados de una consulta
     * @param array $limit Array con los limites de la tabla [inicio, limite] o [limite]
     */
    public function limit( array $limit ) {
        $this->limit = $limit;
    }
        
    /**
     * Asocia tablas a una consutla de tipo <b>select</b>
     * @param array $joins Array con las asociaciones
     * @throws DataBaseServiceException
     */
    public function join(array $joins) {
        if ( $this->type != self::SELECT_QUERY ) {
            throw new DataBaseServiceException(sprintf('Este m&eacute;todo es exclusivo de la acci&oacute;n (%s)', self::SELECT_QUERY));
        }
        
        $this->joins += $joins;
        $this->tables = array_merge($this->tables, array_keys($joins));
    }
    
    /**
     * Asocia tablas a una consutla de tipo <b>select</b>
     * @param string $tableName Nombre de la tabla
     * @param string $mode Modo de relación
     * @param array $relationship Array con las asociaciones
     * @throws DataBaseServiceException
     */
    public function contain($relationship) {
        if ( $this->type != self::SELECT_QUERY ) {
            throw new DataBaseServiceException(sprintf('Este m&eacute;todo es exclusivo de la acci&oacute;n (%s)', self::SELECT_QUERY));
        }
        $this->contains[$relationship['mode'] . '-' . $relationship['alias']] = $relationship;
    }
    
    /**
     * Devuelve los contains many
     * @return array
     */
    public function getContains() {
        return $this->contains;
    }
    
    /**
     * Devuelve los elementos de preparación de resultados
     * @return array
     */
    public function getPrepare() {
        return $this->prepare;
    }
    
    /**
     * Devuelve la consulta construida a partir de los parámetros solicitados
     * @return string
     */
    public function getQuery() {
        switch ($this->type) {
            case self::INSERT_QUERY: $this->query = $this->buildInsertQuery(); break;
            case self::UPDATE_QUERY: $this->query = $this->buildUpdateQuery(); break;
            case self::DELETE_QUERY: $this->query = $this->buildDeleteQuery(); break;
            case self::SELECT_QUERY: $this->query = $this->buildSelectQuery(); break;
        }

        return $this->query;
    }
    /**
     * Devuelve los valores de la consulta <b>update</b> o <b>insert</b>
     * @return array
     */
    public function getValues() {
        return $this->values;
    }
    
    /**
     * Devuelve los parámetros a pasar a PDO
     * @return array
     */
    public function getParams() {
        return ($this->values + $this->condition_fields);
    }
    
    /**
     * Setea el tipo de consulta
     * @param string $type
     */
    public function setType($type) {
        $this->type = $type;
    }
    
    /**
     * Devuelve el tipo de consulta a realizar
     * @return string
     */
    public function getType() {
        return $this->type;
    }
    
    public function debug() {
        return ['query' => $this->query, 'params' => $this->getParams()];
    }
    
    /**
     * Configura una consulta de tipo SELECT
     * @return string
     */
    private function buildSelectQuery() {
        return 'SELECT ' 
            . ($this->fields ? $this->processFields() : '*') 
            . ' FROM ' . $this->table . ($this->tableAlias ? ' AS ' . $this->tableAlias : '')
            . ($this->joins ? $this->processJoin() : NULL)
            . ($this->conditions ? $this->processCondition() : NULL )
            . ($this->order ? $this->processOrder() : NULL)
            . ($this->limit ? $this->processLimit() : NULL);
        
        
    }
    
    /**
     * Configura una consulta de tipo UPDATE
     * @return integer Devuelve el número de filas afectadas
     */
    private function buildUpdateQuery() {
        return 'UPDATE ' . $this->table . ' SET ' 
            . $this->processUpdateValues() 
            . ($this->conditions ? $this->processCondition() : NULL );
    }
    
    /**
     * Configura una consulta de tipo INSERT
     * @return integer Devuelve el ID de la fila insertada
     */
    private function buildInsertQuery() {
        return 'INSERT INTO ' . $this->table . ' (' 
            . $this->processFields() . ') VALUES (' 
            . $this->processInsertValues() . ')'
        ;
    }
    
    /**
     * Configura una consulta de tipo DELETE
     * @return integer Devuelve el número de filas eliminadas
     */
    private function buildDeleteQuery() {
        return 'DELETE FROM ' . $this->table . $this->processCondition();
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
        }, array_keys($this->values));
        
        return implode(', ', $new_values);
    }
    
    /**
     * Procesa los valores a insertar o actualizar de la consulta
     * @return type
     */
    private function processUpdateValues() {
        $changes = array_map(function($key){
            return ' `' . $key . '` = :' . $key;
        }, array_keys($this->values));
        
        return implode(', ', $changes);
    }
    
    /**
     * Procesa los campos de la consulta select o update
     * @return string
     */
    private function processFields() {
        $new_fields = [];
        foreach ( $this->fields as $table => $field ) {
            if ($field == '*') {
                $new_fields[] = '`' . $this->getTableAlias() . '`.*';
                if (count($this->fields) == 1 && $this->tables) {
                    foreach ($this->tables as $joined_table) {
                        $new_fields[] = '`' . $joined_table . '`.*';
                    }
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
                    $new_fields[] = 
                        (is_string($table) && in_array($table, $this->tables) ? '`' . $table . '`.' : '`' . $this->getTableAlias() . '`.') 
                        . '`' . $field . '`' . ( !is_numeric($table) ? ' AS `' . $table . '`' : '')
                    ;
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
        foreach ( $this->order as $sort_mode => $sort_by ) {
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
        return $this->conditions ? ' WHERE ' . $this->parseCondition($this->conditions, $this->joins ? $this->table : NULL) : '';
    }
    
    /**
     * Procesa las asociaciones de tablas
     * @return string
     */
    private function processJoin() {
        $joins = '';
        foreach ($this->joins as $alias => $value) {
            $config = $value + [
                'table' => $alias,
                'type' => 'LEFT',
                'conditions' => NULL
            ];
            if (!array_intersect(array_keys($value), ['table', 'type', 'conditions'])) {
                $config['conditions'] = $value;
            }
            $joins .= ' ' . $config['type'] . ' JOIN ' 
                    . '`' .  $config['table'] . '` ' . ($config['table'] != $alias ? ' AS `' . $alias . '`' : '')
                    . ' ON ' 
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
        if ( empty($this->limit) ) {
            return FALSE;
        }
        
        if ( !key_exists(0, $this->limit) ) {
            throw new DataBaseServiceException('El limit de la consulta esta mal configurado, '
                    . 'asegurese que sea un array simple [start, limit] o [limit]', ['limit' => $this->limit]);
        }
        
        return ' LIMIT ' . (int)$this->limit[0] . ($this->limit[1] ? ', ' . (int)$this->limit[1] : '');
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
            if ($value === TRUE) {
                $value = '1';
            } else if ($value === FALSE) {
                $value = '0';
            } else if ($value === NULL) {
                $value = 'NULL';
            }

            if ( is_string($value) && in_array($value, self::CONDITIONAL_TYPES) ) {
                $op = $value;
                continue;
            }
            
            $isTable = in_array($key, $this->tables) ? $key : NULL;
            $rfield = $forceKey ? $forceKey : $key;
            list ($operator, $field, $fieldTable) = $this->parseField($rfield);
            $array = FALSE;
            
            if ( is_array($value) && $operator !== 'IN' ) {
                $cond .= ' ' . $op . ' (' . $this->parseCondition($value, $isTable, $isTable ? NULL : 'OR', $isTable ? NULL : $key) . ')';
            } else {
                if ($value !== 'NULL' && $prepare) {
                    if ($operator === 'IN' && is_array($value)) {
                        $array = '(' . implode(', ', $value) . ')';
                    } else {
                        $cond_field = 'cnd_' . ($fieldTable ? $fieldTable . '_'  : '') . $field . (is_numeric($key) ? $key : '');
                        $this->condition_fields[$cond_field] = addslashes(trim($value));
                    }    
                } else {
                    list(,$valueField, $valueFieldTable) = $this->parseField($value);
                }
                
                $cond .= ' ' . $op . ' ' . ($table && !$fieldTable ? '`' . $table . '`.' : '') 
                        . ($fieldTable ? '`' . $fieldTable . '`.' : '') 
                        . '`' . $field . '` ' 
                        . $operator . ' '
                        . ($value === 'NULL' 
                            ? $value
                            : ($array
                                ? $array
                                :($prepare 
                                    ? ':' . $cond_field 
                                    : ($valueFieldTable ? '`' . $valueFieldTable . '`.' : '') . '`' . $valueField . '` '
                                )
                            )
                        );
            }
            $op = $op ?: ($initialOperator ? $initialOperator : 'AND');
        }

        return $cond;
    }
    
    /**
     * Prepara para organizar los próximos resultados automáticamente
     * @param string $type
     * @param mix $data
     */
    public function prepare($type, $data){
        $this->prepare[$type] = $data;
    }
    
    /**
     * Devuelve la tabla el operador y el campo encontrado en el field
     * @param string $field
     * @return array
     */
    private function parseField($field) {
        $operator = '=';
        foreach (self::OPERATOR_TYPES as $find) {
            if (preg_match('/ ' . $find . '$/', $field)) {
                $operator = $find;
                $field = trim(str_replace($find, '', $field));
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
    
    /**
     * Devuelve el nombre de la tabla principal
     * @return string
     */
    public function getTableName() {
        return $this->table;
    }
    
    /**
     * Devuelve el nombre alias o el nombre de la tabla principal
     * @return string
     */
    public function getTableAlias($force = FALSE) {
        return $force ? $this->tableAlias : ($this->tableAlias ?: $this->table);
    }
    
    public function addTable($table) {
        $this->tables[] = $table;
    }
}
