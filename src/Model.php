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
use \PDO;
use \PDOException;
/**
 * Modelo de base de datos MySql
 * Se puede modificar para utilizar cualquier otro tipo de base de datos
 * @version 0.1
 * @author Lucas Sosa
 */
class Model {
    /** 
     * Conexión a la base de datos
     * @var PDO 
     */
    private $_service = NULL;
    /**
     * Log de consultas realizadas
     * @var QueryBuilder
     */
    private $_log_queries = [];
    /**
     * Consultas en modo de espera
     * @var QueryBuilder 
     */
    private $_hold_queries = [];
    /**
     * Consulta activa
     * @var QueryBuilder
     */
    private $_active_query = NULL;
    /**
     * Funciones SQL
     * @var Functions
     */
    private $_functions = NULL;
    /**
     * Crea un objeto modelo para la base de datos
     * @param 
     */
    public function __construct(PDO $service) {
        $this->_service = $service;
    }
    
    const DEBUG_FULL = 0;
    const DEBUG_QUERIES = 1;
    const DEBUG_LAST = 2;
    const DEBUG_ACTIVE = 3;
    
    /**
     * Inicia una consulta de tipo SELECT 
     * @param array|string $fields Ejemplos: <pre>
     * <ul>
     * <li><b>Básico</b>: ['field_1', 'field_2'] <i>Campos de la tabla principal</i></li>
     * <li><b>Múltiples tablas</b>: ['field_1', 'field_2', ['joined_table_1' => ['field_3', 'field_4'] ] ] 
     * <i>Campo "field_1" y "field_2" de la tabla principal y campos "field_3" y "field_4" de la tabla "joined_table_1"</i></li>
     * <li><b>Usando máscara</b>: ['field_1', 'other_field' => 'field_2'] <i>Equivalente a </i> <code>SELECT `field_2` AS `other_field`</code></li>
     * </ul>
     * </pre>
     * 
     * @return Query\Select
     */
    public function select($fields = '*') {
        $this->initialize(QueryBuilder::SELECT_QUERY);
        $this->_active_query->fields($fields);
        
        return $this;
    }
    
    /**
     * Modifica los campos de una tabla
     * @param string $table nombre de la tabla
     * @return \PowerOn\Database\Model
     */
    public function update($table) {
        $this->initialize(QueryBuilder::UPDATE_QUERY);
        $this->_active_query->table($table);
        return $this;
    }
    
    /**
     * Desactiva el registro de una tabla
     * @param string $table
     * @return \PowerOn\Database\Model
     */
    public function delete($table) {
        $this->initialize(QueryBuilder::DELETE_QUERY);
        $this->_active_query->table($table);
        return $this;
    }
    
    /**
     * Inserta un nuevo registro en una tabla
     * @param string $table
     * @return \PowerOn\Database\Model
     */
    public function insert($table) {
        $this->initialize(QueryBuilder::INSERT_QUERY);
        $this->_active_query->table($table);
        return $this;
    }
    
    /**
     * Configuración inicial de una consulta
     * @param string $type
     * @return \PowerOn\Database\Model
     */
    private function initialize($type) {
        if ( $this->_active_query !== NULL ) {
            array_push($this->_hold_queries, $this->_active_query);
        }
        $this->_active_query = new QueryBuilder($type);
        
        return $this;
    }
    
    /**
     * Finaliza una consulta y recupera la anterior 
     * en caso de que exista alguna precargada
     */
    private function finalize() {
        $this->_log_queries[] = $this->_active_query;
        $this->_active_query = empty($this->_hold_queries) ? NULL : array_pop($this->_hold_queries);
    }
    
    /**
     * Establece la tabla a trabajar en una consulta <b>select</b>
     * @param string $table Nombre de la tabla
     * @return \PowerOn\Database\Model
     */
    public function from($table) {
        if ( $this->_active_query->getType() != QueryBuilder::SELECT_QUERY ) {
            throw new DataBaseServiceException(
                    sprintf('Este m&eacute;todo es exclusivo de la acci&oacute;n (%s)', QueryBuilder::SELECT_QUERY));
        }
        $this->_active_query->table($table);
        return $this;
    }
    
    /**
     * Establece los campos a ser actualizados en una consulta <b>update</b>
     * @param array $data
     * @return \PowerOn\Database\Model
     */
    public function set(array $data) {
        if ( $this->_active_query->getType() != QueryBuilder::UPDATE_QUERY ) {
            throw new DataBaseServiceException(
                    sprintf('Este m&eacute;todo es exclusivo de la acci&oacute;n (%s)', QueryBuilder::UPDATE_QUERY));
        }
        $this->_active_query->values($data);
        
        return $this;
    }
    
    /**
     * Ingresa los valores a cargar en una consulta <b>insert</b>
     * @param array $data Los valores a insertar
     * @return \PowerOn\Database\Model
     */
    public function values(array $data) {
        if ( $this->_active_query->getType() != QueryBuilder::INSERT_QUERY ) {
            throw new DataBaseServiceException(
                    sprintf('Este m&eacute;todo es exclusivo de la acci&oacute;n (%s)', QueryBuilder::INSERT_QUERY));
        }
        $this->_active_query->fields(array_keys($data));
        $this->_active_query->values($data);
        
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
     *      <td><b>Operador</td><td><code>$cond = ['id >=' => 5]; </code></td><td><i>WHERE `id` >= 5</i></td>
     *  </tr>
     *  <tr>
     *      <td><b>AND</td><td><code>$cond = ['year >=' 2010, 'title LIKE' => 's%']; </code></td>
     *      <td><i>WHERE `year` >= 2010 AND `title` LIKE 's%'</i></td>
     *  </tr>
     *  <tr>
     *      <td><b>OR | AND</td><td><code>$cond = ['id' => 5, 'OR', 'year' => 2017, 'AND', 'title' => ['foo', 'bar'] ]; </code></td>
     *      <td><i>WHERE `id` = 5 OR `year` = 2017 AND (`title` = "foo" OR "title" = "bar")</i></td>
     *  </tr>
     * <tr>
     *      <td><b>Specific Table</td><td><code>$cond = ['authors' => ['movies >=' => 3]]; </code></td>
     *      <td><i>WHERE `authors`.`movies` >= 3</i></td>
     *  </tr>
     * </table>
     * </pre>
     * @param array $conditions
     * @return \PowerOn\Database\Model
     */
    public function where(array $conditions) {
        $this->_active_query->conditions($conditions);
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
        $this->_active_query->order($order);
        
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
        $this->_active_query->limit( [$start_limit, $end_limit] );
        
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
        if ( $this->_active_query->getType() != QueryBuilder::SELECT_QUERY ) {
            throw new DataBaseServiceException(sprintf('Este m&eacute;todo es exclusivo de la acci&oacute;n (%s)', QueryBuilder::SELECT_QUERY));
        }
        
        $this->_active_query->join( $joins );

        return $this;
    }
    
    /**
     * Agrega campo adicionales a la consulta
     * @param string $fields Campos a agregar
     */
    public function fields($fields) {
        $this->_active_query->fields($fields);
    }
    
    /**
     * Ejecuta una operación de insert o update
     * @return \PowerOn\Database\Model
     */
    public function execute() {
        if ( $this->_active_query->getType() == QueryBuilder::SELECT_QUERY ) {
            throw new DataBaseServiceException(sprintf('Este m&eacute;todo es exclusivo de las acciones (%s, %s, %s)', 
                    QueryBuilder::INSERT_QUERY, QueryBuilder::UPDATE_QUERY, QueryBuilder::DELETE_QUERY));
        }
        $query = $this->_active_query->getQuery();
        $params = $this->_active_query->getParams();
        
        return $this->query($query, $params);
    }
    
    /**
     * Devuelve todos los resultadaos
     * @return \PowerOn\Database\Query
     */
    public function all() {
        $query = $this->query( $this->_active_query->getQuery(), $this->_active_query->getParams() );
        return new QueryResult($query);
    }

    /**
     * Devuelve el primer resultado
     * @return \PowerOn\Database\Query
     */
    public function first() {
        $this->limit(1);

        $query = $this->query( $this->_active_query->getQuery(), $this->_active_query->getParams() );
        return (new QueryResult($query, TRUE))->toArray();
    }
    
    /**
     *  Devuelve el ultimo resultado
     * @return \PowerOn\Database\Query
     */
    public function last() {
        $this->_active_query->order(['DESC' => 'id']);
        return $this->first();
    }
    
    /**
     * Devulve el campo con ID específico
     * @param integer $id
     * @return \PowerOn\Database\Query
     */
    public function id($id) {
        $this->_active_query->conditions(['id' => $id]);
        return $this->first();
    }
    
    /**
     * Devuelve la cantidad de resultados encontrados
     * @return integer
     */
    public function count() {
        $this->_active_query->fields($this->func()->count());
        $query = $this->query( $this->_active_query->getQuery(), $this->_active_query->getParams() );
        return (int)$query->rowCount();
    }
    /**
     * Crea una función SQL
     * @param string $function Nombre de la función
     * @param string $params Parámetros
     * @return string
     */
    public function func() {
        if ( $this->_functions === NULL ) {
            $this->_functions = new Functions();
        }
        
        return $this->_functions;
    }
    
    /**
     * Devuelve la consulta configurada por realizar
     * @return string
     */
    public function debug() {
        $args = func_get_args();
        $debug = [];
        
        if ( in_array(self::DEBUG_LAST, $args) ) {
            $debug = end($this->_log_queries);
        }else if (in_array(self::DEBUG_ACTIVE, $args) ) {
            $debug = $this->_active_query;
        } else {
            $debug = $this->_log_queries;
        }
        
        if ( in_array(self::DEBUG_QUERIES, $args) ) {
            $new_debug = [];
            foreach ($debug as $db) {
                $new_debug[] = $db->debug();
            }
            
            $debug = $new_debug;
        }
        
        return $debug;
    }
    
    /**
     * Devuelve el log de consultas realizadas
     * @return array
     */
    public function getQueryLog() {
        return $this->_log_query;
    }
        
    /**
     * Ejecuta la consulta solicitada
     * 
     * @param string $query La consulta en la base de datos
     * @param array $params Parámetros a incluir en la consulta
     * @return \PDOStatement Devuelve el resultado de la consulta, o FALSE en caso de error
     */
    private function query($query, array $params = []) {
        try {
            $data = $this->_service->prepare($query);
            
            $this->_service->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            $data->execute($params);
        } catch (PDOException $e) {
            throw new DataBaseServiceException('Error al realizar la consulta MySql', [
                    'sql_code' => $e->getCode(),
                    'sql_message' => $e->getMessage(),
                    'sql_query' => $query,
                    'params' => $params
                ]
            );
        }

        $result = $this->_active_query->getType() == QueryBuilder::INSERT_QUERY ? $this->_service->lastInsertId() :
            ($this->_active_query->getType() == QueryBuilder::SELECT_QUERY ? $data : $data->rowCount());
        
        $this->finalize();
        
        return $result;
    }
}
