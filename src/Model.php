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
use function \PowerOn\Application\is_json;

/**
 * Modelo de base de datos MySql
 * Se puede modificar para utilizar cualquier otro tipo de base de datos
 * @version 0.1
 * @author Lucas Sosa
 */
class Model {
    /** 
     * Conexión a la base de datos
     * @var \mysqli 
     */
    private $_connect = NULL;
    /**
     * Nombre de la tabla principal
     * @var string
     */
    protected $_table_name = NULL;
    /**
     * Array con las tablas de la consulta
     * @var array
     */
    private $_tables = [];
    /**
     * Campos a seleccionar de la consulta
     * @var string
     */
    protected $_fields = [];
    /**
     * Condiciones de la consulta procesadas
     * @var string
     */
    private $_conditions = NULL;
    /**
     * Tablas a incluir en la consulta procesadas
     * @var string
     */
    private $_joins = NULL;
    /**
     * Limit de consulta procesado
     * @var string
     */
    private $_limit = NULL;
    /**
     * Valores a modificar en una consulta UPDATE procesados
     * @var string
     */
    private $_values = NULL;
    /**
     * Tipo de acción realizada
     * [insert, update, select]
     * @var action
     */
    private $_activity = NULL;
    /**
     * Order de la consulta procesado
     * @var string
     */
    private $_order = NULL;
    /**
     * Log de consultas 
     * @var array
     */
    private $_log_query = [];
    /**
     * Consulta en modo espera
     * @var array
     */
    private $_stand_query = [];
    
    const SELECT_QUERY = 'select';
    const UPDATE_QUERY = 'update';
    const DELETE_QUERY = 'delete';
    const INSERT_QUERY = 'insert';
    
    /**
     * Crea un objeto modelo para la base de datos
     * @param string $host Direccion del host de la base de datos
     * @param string $user Usuario
     * @param string $password Contraseña
     * @param string $database Nombre de la base de datos que va a utilizar
     * @param integer $port [Opcional] Puerto a utilizar, por defecto es el 3306
     * @throws DevException
     */
    public function __construct(\mysqli $connect) {
        $this->_connect = $connect;
    }
    
    /**
     * Cambia la base de datos en la que se esta trabajando
     * @param string $database
     */
    public function setDataBase($database) {
        @$this->_connect->select_db($database);
        if ( $this->_connect->errno ) {
            throw new DevException(sprintf('La base de datos (%s) no existe', $database), [
                    'mysql_code' => $this->_connect->errno, 
                    'mysql_message' => $this->_connect->error
                ]
            );
        }
    }

    /**
     * Inicia una consulta de tipo SELECT
     * @param type $table El nombre de la tabla a buscar
     * @return \PowerOn\Database\Model
     */
    public function find($table) {
        return $this->initialize($table, self::SELECT_QUERY);
    }
    
    /**
     * Modifica los campos de una tabla
     * @param string $table nombre de la tabla
     * @return \PowerOn\Database\Model
     */
    public function update($table) {
        return $this->initialize($table, self::UPDATE_QUERY);
    }
    
    /**
     * Desactiva el registro de una tabla
     * @param string $table
     * @return \PowerOn\Database\Model
     */
    public function delete($table) {
        return $this->initialize($table, self::DELETE_QUERY);
    }
    
    /**
     * Inserta un nuevo registro en una tabla
     * @param string $table
     * @return \PowerOn\Database\Model
     */
    public function insert($table) {
        return $this->initialize($table, self::INSERT_QUERY);
    }
    
    /**
     * Configuración inicial de una consulta
     * @param type $table
     * @param type $activity
     * @return \PowerOn\Database\Model
     */
    private function initialize($table, $activity) {
        if ( $this->_activity ) {
            array_push($this->_stand_query, [
                '_table_name' => $this->_table_name,
                '_activity' => $this->_activity,
                '_conditions' => $this->_conditions,
                '_joins' => $this->_joins,
                '_fields' => $this->_fields,
                '_limit' => $this->_limit,
                '_values' => $this->_values,
                '_order' => $this->_order,
                '_tables' => $this->_tables,
            ]);
        }
        
        $this->_table_name = $table;
        $this->_activity = $activity;
        $this->_conditions = [];
        $this->_joins = [];
        $this->_fields = [];
        $this->_limit = [];
        $this->_values = [];
        $this->_order = [];
        $this->_tables = [];

        return $this;
    }
    
    private function finalize() {
        if ( $this->_stand_query ) {
            $last_stand = end($this->_stand_query);
            foreach ($last_stand as $sq) {
                $this->{ $sq };
            }
            array_pop($this->_stand_query);
        } else {
            $this->_activity = NULL;
            $this->_table_name = NULL;
        }
    }
    
    /**
     * Los campos a seleccionar. Ejemplos: <pre>
     * <ul>
     * <li><b>Básico</b>: ['field_1', 'field_2'] <i>Campos de la tabla principal</i></li>
     * <li><b>Múltiples tablas</b>: ['field_1', 'field_2', ['joined_table_1' => ['field_3', 'field_4'] ] ] 
     * <i>Campo "field_1" y "field_2" de la tabla principal y campos "field_3" y "field_4" de la tabla "joined_table_1"</i></li>
     * <li><b>Usando máscara</b>: ['field_1', 'other_field' => 'field_2'] <i>Equivalente a </i> <code>SELECT `field_2` AS `other_field`</code></li>
     * </ul>
     * </pre>
     * @param array|string $fields
     * @return \PowerOn\Database\Model
     */
    public function select($fields) {
        $this->_fields = is_array($fields) ? $fields : [$fields];

        return $this;
    }
        
    /**
     * Establece los campos a ser actualizados
     * @param array $data
     * @return \PowerOn\Database\Model
     */
    public function set(array $data) {
        if ( $this->_activity != self::UPDATE_QUERY ) {
            throw new DevException(sprintf('Este m&eacute;todo es exclusivo de la acci&oacute;n (%s)', self::UPDATE_QUERY));
        }
        $this->_values = $data;
        return $this;
    }
    
    /**
     * Ingresa los valores a cargar en una consulta INSERT
     * @param array $data Los valores a insertar
     * @return \PowerOn\Database\Model
     */
    public function values(array $data = []) {
        if ( $this->_activity != self::INSERT_QUERY ) {
            throw new DevException(sprintf('Este m&eacute;todo es exclusivo de la acci&oacute;n (%s)', self::INSERT_QUERY));
        }
        
        $this->_fields = array_keys($data);
        $this->_values = array_values($data);
        
        return $this;
    }
   
    /**
     * Completa las condiciones de cualquier consulta, Ejemplos: 
     * <pre>
     * <table border=1 width=100%>
     *  <tr>
     *      <td>Descripción</td><td>Código</td><td>Salida SQL</td>
     *  </tr>
     *  <tr>
     *      <td><b>Básico</b></td><td><code>$cond = ['id' => 2]; </code></td><td><i>WHERE `id` = 2</i></td>
     *  </tr>
     *  <tr>
     *      <td><b>Operador</td><td><code>$cond = ['id' => ['>=', 5]]; </code></td><td><i>WHERE `id` >= 5</i></td>
     *  </tr>
     *  <tr>
     *      <td><b>AND</td><td><code>$cond = ['year' => ['>=', 2010], 'title' => ['LIKE', 's%']]; </code></td>
     *      <td><i>WHERE `year` >= 2010 AND `title` LIKE 's%'</i></td>
     *  </tr>
     *  <tr>
     *      <td><b>OR | AND</td><td><code>$cond = ['id' => 5, 'OR', 'year' => 2017, 'AND', 'title' => ['foo', 'bar'] ]; </code></td>
     *      <td><i>WHERE `id` = 5 OR `year` = 2017 AND (`title` = "foo" OR "title" = "bar")</i></td>
     *  </tr>
     * <tr>
     *      <td><b>Specific Table</td><td><code>$cond = ['authors' => ['movies' => ['>=', 3]]]; </code></td>
     *      <td><i>WHERE `authors`.`movies` >= 3</i></td>
     *  </tr>
     * </table>
     * </pre>
     * @param array $conditions Ej: ['field' => 'value, 'OR', 'table' => ['type' => 'client', 'AND', 'type' => 'provider'], 'field' => ['value1', 'value2'] ]
     * @return \PowerOn\Database\Model
     */
    public function where(array $conditions) {
        $this->_conditions = $conditions;

        return $this;
    }
        
    /**
     * Ordena los resultados de una consulta, Ejemplo: 
     * <pre>
        * <code>
        * $order = ['DESC' => ['id', 'lastname'], 'ASC' => 'name'] 
        * </code>
        * <i>El resultado sería ORDER BY `id` DESC, `lastname` DESC, `name` ASC</i>
     * </pre>
     * @param array $order Array estableciendo el orden
     * @return \PowerOn\Database\Model
     */
    public function order( array $order ) {
        $this->_order = $order;
        
        return $this;
    }
    
    /**
     * Limita los resultados de una consulta
     * @param integer $start_limit Cantidad de resultados a mostrar ( Si se especifica el segundo parámetro 
     * entonces este parámetro indica donde comienzan los resultados
     * @param integer $end_limit [Opcional] Reservado para paginación de resultados, cantidad máxima de resultados a mostrar por página
     * @return \PowerOn\Database\Model
     */
    public function limit( $start_limit, $end_limit = NULL ) {
        $this->_limit = [$start_limit, $end_limit];
        
        return $this;
    }
        
    /**
     * Asocia una o varias tablas Ejemplo:
     * <pre>
     * <ul>
     *  <li><b>Básico</b>: ['table_join' => [ 'join_field_name' => ['table_field', 'table_name', 'operator(=|!=|<=|>=)', 'type(LEFT|INNER)'] ],
     *  'table_join_2' => ...]</li>
     * </ul>
     * </pre>
     * @param array $joins Array con las asociaciones
     * @return \PowerOn\Database\Model
     */
    public function join(array $joins) {
        if ( $this->_activity != self::SELECT_QUERY ) {
            throw new DevException(sprintf('Este m&eacute;todo es exclusivo de la acci&oacute;n (%s)', self::SELECT_QUERY));
        }
        
        $this->_joins = $joins;

        return $this;
    }
    
    /**
     * Devuelve un array con los datos de la columna especificada
     * @param string $field nombre de la columna
     * @return array
     */
    public function column($field) {
        $query = $this->runQuery($this->getSelectQuery());
        $column = [];
        while( $result = $query->fetch_assoc() ) {
            $column[] = key_exists($field, $result) ? $result[$field] : NULL;
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
    public function columnCombine($field_value, $field_key = 'id') {
        $query = $this->runQuery($this->getSelectQuery());
        $column = [];
        while ($result = $query->fetch_assoc() ) {
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
     * Devuelve los datos de una columna sola de un ID especificado
     * @param string $field Nombre de la columna
     * @param integer $id Numero de ID de la tabla
     * @return array
     */
    public function columnID($field, $id) {
        $results = $this->id($id);
        return key_exists($field, $results) ? $results[$field] : NULL;
    }
    
    /**
     * Devuelve todos los resultadaos
     * @return array
     */
    public function all() {
        $query = $this->runQuery($this->getSelectQuery());
        return $query->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Devuelve la cantidad de resultados encontrados
     * @return integer
     */
    public function count() {
        $query = $this->runQuery($this->getSelectQuery());
        return $query->num_rows;
    }
    
    /**
     * Devuelve todos los resultadaos utilizando el campo ID como indice
     * @return array
     */
    public function allByID() {
        $query = $this->runQuery($this->getSelectQuery());
        $results = [];
        while ( $data = $query->fetch_all(MYSQLI_ASSOC) ) {
            $results[key_exists('id', $data) ? $data['id'] : count($results)] = $data;
        }
        return $results;
    }
    
    /**
     * Devuelve el primer resultado
     * @return array
     */
    public function first() {
        $this->limit(1);
        $query = $this->runQuery($this->getSelectQuery());
        return $query->fetch_assoc();
    }
    
    /**
     *  Devuelve el ultimo resultado
     * @return arrat
     */
    public function last() {
        $this->order(['DESC' => 'id']);
        return $this->first();
    }
    
    /**
     * Devulve el campo con ID específico
     * @param integer $id
     * @return \PowerOn\Database\Model
     */
    public function id($id) {
        return $this->where(['id' => $id])->first();
    }
    
    /**
     * Ejecuta una operación de insert o update
     * @return \PowerOn\Database\Model
     */
    public function execute() {
        if ( $this->_activity == self::SELECT_QUERY ) {
            throw new DevException(sprintf('Este m&eacute;todo es exclusivo de las acciones (%s, %s, %s)', 
                    self::INSERT_QUERY, self::UPDATE_QUERY, self::DELETE_QUERY));
        }
        
        switch ($this->_activity) {
            case self::INSERT_QUERY:    return $this->runQuery($this->getInsertQuery());
            case self::UPDATE_QUERY:    return $this->runQuery($this->getUpdateQuery());
            case self::DELETE_QUERY:    return $this->runQuery($this->getDeleteQuery());
            default: throw new DevException(sprintf('No se reconoce la actividad (%s)', $this->_activity));
        }
    }
    
    /**
     * Devuelve la consulta configurada por realizar
     * @return string
     */
    public function debug() {
        switch ($this->_activity) {
            case self::INSERT_QUERY:    return $this->getInsertQuery();
            case self::UPDATE_QUERY:    return $this->getUpdateQuery();
            case self::DELETE_QUERY:    return $this->getDeleteQuery();
            case self::SELECT_QUERY:    return $this->getSelectQuery();
            default:                    return $this->_log_query;
        }
    }
    
    /**
     * Devuelve el log de consultas realizadas
     * @return array
     */
    public function getQueryLog() {
        return $this->_log_query;
    }
    
    /**
     * Devuelve los campos configurados para una consulta
     * @return array
     */
    public function getFields() {
        return $this->_fields;
    }
    
    /**
     * Agrega múltiples campos adicionales a la consulta
     * @param mix $fields
     */
    public function pushFields($fields) {
        $this->_fields += is_array($fields) ? $fields : [$fields];
    }
    
    /**
     * Configura una consulta de tipo SELECT
     * @return string
     */
    private function getSelectQuery() {
        return 'SELECT ' 
            . ($this->_fields ? $this->processFields() : '*') 
            . ' FROM ' . $this->_table_name
            . ($this->_joins ? $this->processJoin() : NULL)
            . ($this->_conditions ? $this->processCondition() : NULL )
            . ($this->_order ? $this->processOrder() : NULL)
            . ($this->_limit ? $this->processLimit() : NULL);
        
        
    }
    
    /**
     * Configura una consulta de tipo UPDATE
     * @return integer Devuelve el número de filas afectadas
     */
    private function getUpdateQuery() {
        return 'UPDATE ' . $this->_table_name . ' SET ' 
            . $this->processUpdateValues() 
            . ($this->_conditions ? $this->processCondition() : NULL );
    }
    
    /**
     * Configura una consulta de tipo INSERT
     * @return integer Devuelve el ID de la fila insertada
     */
    private function getInsertQuery() {
        return 'INSERT INTO ' . $this->_table_name . ' (' 
            . $this->processFields() . ') VALUES (' 
            . $this->processInsertValues() . ')'
        ;
    }
    
    /**
     * Configura una consulta de tipo DELETE
     * @return integer Devuelve el número de filas eliminadas
     */
    private function getDeleteQuery() {
        if ( !$this->_conditions ) {
            throw new DevException('Atenci&oacute;n, esta funci&oacute;n eliminar&aacute; todo el contenido de la tabla, para poder realizar '
                    . 'esta acci&oacute;n debe realizarse a trav&eacute;s del administrador de su base de datos con la funci&oacute;n TRUNCATE');
        }
        return 'DELETE FROM ' . $this->_table_name . $this->processCondition();
    }
    
    /**
     * Ejecuta la consulta solicitada
     * 
     * @param String $query La consulta en la base de datos
     * @return \mysqli_result Devuelve el resultado de la consulta, o FALSE en caso de error
     */
    private function runQuery($query) {
            $this->_log_query[] = $query;
            $data = $this->_connect->query($query);

            if ( $this->_connect->error ) {
                throw new DevException('Error al realizar la consulta MySql', [
                        'mysql_code' => $this->_connect->errno,
                        'mysql_message' => $this->_connect->error,
                        'mysql_query' => $query,
                        'conditions' => $this->_conditions
                    ]
                );
            }
            
            $return = $this->_activity == self::INSERT_QUERY ? $this->_connect->insert_id : $data;
            $this->finalize();
            
            return $return;
    }
        
    /**
     * Procesa los valores a insertar o actualizar de la consulta
     * @return type
     */
    private function processInsertValues() {
        $new_values = [];
        foreach ($this->_values as $value) {
            $new_values[] = is_json($value) ? '\'' . $value . '\'' : 
                '"' . $this->_connect->real_escape_string(addslashes(trim($value))) . '"';
        }
        
        return implode(', ', $new_values);
    }
    
    /**
     * Procesa los valores a insertar o actualizar de la consulta
     * @return type
     */
    private function processUpdateValues() {
        $changes = [];
        foreach( $this->_values as $field => $value ) {
            $v = is_numeric($value) ? $value : 
                    (is_json($value) ? "'" . $value . "'" : 
                '"' . $this->_connect->real_escape_string(trim(addslashes($value))) . '"');
            array_push($changes, ' `' . $field . '` = ' . $v);
        }
        return implode(', ', $changes);
    }
    
    /**
     * Procesa los campos de la consulta select o update
     * @return string
     */
    private function processFields() {
        $new_fields = [];
        foreach ( $this->_fields as $table => $field ) {
            if ( is_array($field) ) {
                $new_sub_fields = [];
                foreach ($field as $mask => $sub_field) {
                    $new_sub_fields[] = '`' . $this->_connect->real_escape_string($table) . '`.`' 
                            . $this->_connect->real_escape_string($sub_field) . '` ' 
                            . ( !is_numeric($mask) ? ' AS `' . $this->_connect->real_escape_string($mask) . '`' : '');
                    
                }
                $new_fields[] = implode(',', $new_sub_fields);
            } else {
                $new_fields[] = $field == '*' && !in_array($table, $this->_tables) 
                        ? '`' . $this->_connect->real_escape_string($this->_table_name) . '`.*' : (
                        (is_string($table) ? '`' . $this->_connect->real_escape_string($table) . '`.' : '')
                    . ($field == '*' ? '*' : '`' . $this->_connect->real_escape_string($field) . '`'));
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
                $sorts[] = '`' . $this->_connect->real_escape_string($field) . '` ' . (strtoupper($sort_mode) == 'DESC' ? 'DESC' : 'ASC');
            }
        }
        return ' ORDER BY ' . implode(', ', $sorts);
    }
        
    private function processCondition() {
        return ' WHERE ' . $this->parseCondition($this->_conditions, $this->_joins ? $this->_table_name : NULL);
    }
    
    /**
     * Procesa las asociaciones de tablas
     * @return string
     */
    private function processJoin() {
        $join_types = ['INNER', 'LEFT', 'RIGHT', 'LEFT OUTER', 'RIGHT OUTER'];
        $op_types = ['LIKE', '=', '!=', '<', '>', '<=', '>=', 'NOT', 'NOT LIKE', 'JSON'];
        $all_joins = '';

        foreach ($this->_joins as $key => $value) {
            $p = '';
            $tb = $key;
            $s = explode('AS', $key);
            if ( count($s) > 1 ) {
                $key = trim($s[1]);
                $tb = trim($s[0]);
                $p = ' ' . $s[1] . '';
            }
            $this->_tables[] = $key;
            $cfg = reset($value);
            $all_joins .= ' ' . (key_exists(3, $cfg) && in_array($cfg[3], $join_types) ? $cfg[3] : 'LEFT') . ' JOIN `' 
                    . $tb . '` ' . $p . ' ON `' . $key . '`.`' . key($value) . '` ' 
                    . (key_exists(2, $cfg) && in_array($cfg[2], $op_types) ? $cfg[2] : '=') . ' `' 
                    . (key_exists(1, $cfg) ? $cfg[1] : $this->_table_name) . '`.`' . $cfg[0] . '`';
        }
        
        return $all_joins;
    }
    
    /**
     * Procesa el limit de la consulta
     * @return string
     */
    private function processLimit() {
        return ' LIMIT ' . (int)$this->_limit[0] . ($this->_limit[1] ? ', ' . (int)$this->_limit[1] : '');
    }
    
    /**
     * Procesa una lista de condiciones en aray
     * @param array $conditions Las condiciones
     * @param string $table Nombre de la tabla inicial
     * @param string $initial_operator Operador Inicial
     * @param string $force_key Clave forzada
     * @return string La consulta procesada compelta
     */
    private function parseCondition(array $conditions, $table = NULL, $initial_operator = NULL, $force_key = NULL) {
        $at = ['AND', 'OR', 'AND NOT', 'OR NOT', 'NOT'];
        $ao = ['LIKE', '=', '!=', '<', '>', '<=', '>=', 'NOT', 'NOT LIKE', 'JSON'];
        $cond = '';
        $op = '';
        foreach ($conditions as $key => $value) {
            if ($value === NULL) {
                continue;
            }
            if (is_array($value) && !$value ) {
                $value = 'NULL';
            }
            if ( is_string($value) && in_array($value, $at) ) {
                $op = $value;
                continue;
            }

            if ( in_array($key, $this->_tables) && is_array($value) ) {
                $cond .= ' ' . $op . ' (' . $this->processCondition($value, $key) . ')';
            } else if ( is_array($value) && is_array(reset($value)) ) {
                $cond .= ' ' . $op . ' (';
                $op2 = '';
                foreach ($value as $v) {
                    $cond .= ' ' . $op2 . ' ' . $this->processCondition([$key => $v], NULL, 'OR', $key);
                    if ( is_string($v) && in_array($v, $at) ) {
                        $op2 = $v;
                    } else {
                        $op2 = 'OR';
                    }
                }
                $cond .= ')';
            } else if ( is_array($value) && !in_array(reset($value), $ao, TRUE) ) {
                $cond .= ' ' . $op . ' (' . $this->processCondition($value, NULL, 'OR', $key) . ')';
            } else {
                if ( is_array($value) && $value[0] == 'JSON' ) {
                    $value_process = '%\"' . addslashes(trim($value[1])) . '\"%';
                } else {
                    $value_process = addslashes(trim((!is_array($value) ? $value : $value[1])));
                }
                
                $cond .= ' ' . $op . ' ' . ($table ? '`' . $table . '`.' : '') . '`' .
                        ($force_key ? $force_key : $key) . '` ' . 
                        ( !is_array($value) || (!in_array($value[0], $ao)) ? '=' : 
                            ($value[0] == 'JSON' ? 'LIKE' : $value[0])
                        ) . 
                        ' "' . $value_process . '" ';
            }
            $op = !$op ? ($initial_operator ? $initial_operator : 'AND') : $op;
        }

        return $cond;
    }
}
