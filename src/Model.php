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
use PowerOn\Utility\Inflector;

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
    private $service = NULL;
    /**
     * Log de consultas realizadas
     * @var QueryBuilder
     */
    private $query_log = [];
    /**
     * Consultas en modo de espera
     * @var QueryBuilder 
     */
    private $query_hold = [];
    /**
     * Consulta activa
     * @var QueryBuilder
     */
    private $query_active = NULL;
    /**
     * Funciones SQL
     * @var Functions
     */
    private $functions = NULL;
    
    /**
     * Crea un objeto modelo para la base de datos
     * @param 
     */
    public function __construct(PDO $service) {
        $this->service = $service;
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
        $this->query_active->fields($fields);
        
        return $this;
    }
    
    /**
     * Modifica los campos de una tabla
     * @param string $table nombre de la tabla
     * @return \PowerOn\Database\Model
     */
    public function update($table) {
        $this->initialize(QueryBuilder::UPDATE_QUERY);
        $this->query_active->table($table);
        return $this;
    }
    
    /**
     * Desactiva el registro de una tabla
     * @param string $table
     * @return \PowerOn\Database\Model
     */
    public function delete($table) {
        $this->initialize(QueryBuilder::DELETE_QUERY);
        $this->query_active->table($table);
        return $this;
    }
    
    /**
     * Inserta un nuevo registro en una tabla
     * @param string $table
     * @return \PowerOn\Database\Model
     */
    public function insert($table) {
        $this->initialize(QueryBuilder::INSERT_QUERY);
        $this->query_active->table($table);
        return $this;
    }
    
    /**
     * Configuración inicial de una consulta
     * @param string $type
     * @return \PowerOn\Database\Model
     */
    private function initialize($type) {
        if ( $this->query_active !== NULL ) {
            array_push($this->query_hold, $this->query_active);
        }
        $this->query_active = new QueryBuilder($type);
        
        return $this;
    }
    
    /**
     * Finaliza una consulta y recupera la anterior 
     * en caso de que exista alguna precargada
     */
    private function finalize() {
        $this->query_log[] = $this->query_active;
        $this->contains_many = [];
        $this->contains_one = [];
        $this->query_active = empty($this->query_hold) ? NULL : array_pop($this->query_hold);
    }
    
    /**
     * Establece la tabla a trabajar en una consulta <b>select</b>
     * @param string $table Nombre de la tabla
     * @return \PowerOn\Database\Model
     */
    public function from($table) {
        if ( $this->query_active->getType() != QueryBuilder::SELECT_QUERY ) {
            throw new DataBaseServiceException(
                    sprintf('Este m&eacute;todo es exclusivo de la acci&oacute;n (%s)', QueryBuilder::SELECT_QUERY));
        }
        $this->query_active->table($table);
        return $this;
    }
    
    /**
     * Establece los campos a ser actualizados en una consulta <b>update</b>
     * @param array $data
     * @return \PowerOn\Database\Model
     */
    public function set(array $data) {
        if ( $this->query_active->getType() != QueryBuilder::UPDATE_QUERY ) {
            throw new DataBaseServiceException(
                    sprintf('Este m&eacute;todo es exclusivo de la acci&oacute;n (%s)', QueryBuilder::UPDATE_QUERY));
        }
        $this->query_active->values($data);
        
        return $this;
    }
    
    /**
     * Ingresa los valores a cargar en una consulta <b>insert</b>
     * @param array $data Los valores a insertar
     * @return \PowerOn\Database\Model
     */
    public function values(array $data) {
        if ( $this->query_active->getType() != QueryBuilder::INSERT_QUERY ) {
            throw new DataBaseServiceException(
                    sprintf('Este m&eacute;todo es exclusivo de la acci&oacute;n (%s)', QueryBuilder::INSERT_QUERY));
        }
        $this->query_active->fields(array_keys($data));
        $this->query_active->values($data);
        
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
        $this->query_active->conditions($conditions);
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
        $this->query_active->order($order);
        
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
        $this->query_active->limit( [$start_limit, $end_limit] );
        
        return $this;
    }
        
    /**
     * Asocia una o varias tablas Ejemplo:
     * <pre>
     * <ul>
     *  <li><b>Básico</b>: ['join_table_1' => (array) conditions, 'join_table_2' => (array) conditions, ...] </li>
     *  <li><b>Avanzado</b>: ['join_table_1' => ['type' => 'LEFT|RIGHT|INNER|OUTER', 'conditions' => (array) conditions]] </li>
     * </ul>
     * </pre>
     * @param array $joins Array con las asociaciones
     * @return \PowerOn\Database\Model
     */
    public function join(array $joins) {
        if ( $this->query_active->getType() != QueryBuilder::SELECT_QUERY ) {
            throw new DataBaseServiceException(sprintf('Este m&eacute;todo es exclusivo de la acci&oacute;n (%s)', QueryBuilder::SELECT_QUERY));
        }
        
        $this->query_active->join( $joins );

        return $this;
    }
    
    /**
     * Asocia una o varias tablas y las incluye en un elemento independiente del array:
     * <ul>
     *  <li><b>Básico</b>: ['table_1' => (array) conditions, 'alias_table_2' =>
     *  ['table' => 'table_name', 'conditions' => (array) conditions, ...] </li>
     *  <li><b>Avanzado</b>: ['join_table_1' => 
     * ['table' => (string) real-table-name, 'conditions' => (array) conditions, 'contain' => (array) sub-contains, 'fields' => ...]] </li>
     * </ul>
     * Si no se especifica el alias de cada array se utiliza el nombre de la tabla en singular
     * <pre>
     * Ejemplo: 
     *   $database
     *      ->select()
     *      ->from('users')
     *      ->contain([
     *          [
     *              'table' => 'employees',
     *              'conditions' => ['employee.user_id' => 'users.id']
     *          ]
     *      )
     *  ;
     * </pre>
     * El resultado sería:
     * <pre>
     * (array) [
     *   'id' => 9,
     *   'username' => 'usertest',
     *   'password' => 'xxxx',
     *   'name' => 'Mr. Example',
     *   ...
     *   'employee' => [
     *      'id' => 27,
     *      'first_name' => 'Carlos',
     *      'last_name' => 'Sanchez',
     *      'legacy' => 'MT-230'
     *      ...
     *   ]
     * ]
     * </pre>
     * @param array|string $contains Array con las asociaciones
     * @return \PowerOn\Database\Model
     */
    public function containOne($contains) {
        if ( $this->query_active->getType() != QueryBuilder::SELECT_QUERY ) {
            throw new DataBaseServiceException(sprintf('Este m&eacute;todo es exclusivo de la acci&oacute;n (%s)', QueryBuilder::SELECT_QUERY));
        }
        $joins = [];
        $fields = [];
        
        if (is_string($contains)) {
            $contains = [$contains];
        }
        
        foreach ($contains as $alias => $data) {
            $cfg = (is_string($data) ? ['table' => $data] : $data) + [
                'fields' => '*',
                'conditions' => NULL,
                'table' => $alias
            ];
            
            if ( is_numeric($alias) && $cfg['table'] && !is_numeric($cfg['table']) ) {
                $alias = Inflector::singularize($cfg['table']);
            }
            
            if ( !$cfg['conditions'] ) {
                $cfg['conditions'] = [$alias . '.id' => Inflector::singularize($cfg['table']) . '_id'];
            }
            
            $this->query_active->containOne([$alias => $cfg['table']]);
            
            $joins[$alias] = ['table' => $cfg['table'], 'conditions' => $cfg['conditions']];
            if ($cfg['fields'] == '*') {
                $query = $this->service->query('SHOW COLUMNS FROM `' . $cfg['table'] . '`');
                while ($column = $query->fetch(PDO::FETCH_ASSOC)) {
                    $fields[$alias]['__contain_' . $alias . '__' . $column['Field']] = $column['Field'];
                }
            } else {
                foreach ($cfg['fields'] as $field) {
                    $fields[$alias]['__contain_' . $alias . '__' . $field] = $field;
                }
            }
        }
        
        $this->query_active->join( $joins );
        
        if ($fields) {
            $this->query_active->fields($fields);
        }
        
        return $this;
    }
    
    /**
     * Igual que containOne pero con múltiples resultados 
     * <pre>
     * $users = $database->select()->from('users')->containMany('articles' => ['articles.user_id' => 'users.id'])->all()->toArray();
     * Resultado:
     * (array) $users [
     *  'id' => 9
     *  'username' => 'usertest'
     *  ...
     *  'articles' => (array) [
     *   [
     *    'id' => 872
     *    'title' => 'Art 1'
     *    ...
     *   ]
     *   [
     *    'id' => 873
     *    'title' => 'Art 2'
     *    ...
     *   ]
     *   ...
     *  ]
     * ]
     * </pre>
     * @see containOne
     * @param array $contains
     * @throws DataBaseServiceException
     */
    public function containMany($contains) {
        if ( $this->query_active->getType() != QueryBuilder::SELECT_QUERY ) {
            throw new DataBaseServiceException(sprintf('Este m&eacute;todo es exclusivo de la acci&oacute;n (%s)', QueryBuilder::SELECT_QUERY));
        }
        
        if (is_string($contains)) {
            $contains = [$contains];
        }

        foreach ($contains as $alias => $containData) {
            if ( is_string($containData) ) {
                $containData = [
                    'table' => $containData
                ];
            }
            $contain = $containData + [
                'table' => $alias,
                'key' => Inflector::singularize($this->query_active->getTableName()) . '_id',
                'parentKey' => 'id',
                'fields' => '*',
                'by' => NULL,
                'order' => [],
                'join' => [],
                'containOne' => [],
                'containMany' => [],
                'limit' => NULL,
                'conditions' => [],
                'combine' => NULL,
                'column' => NULL
            ];
            
            if (is_array($contain['fields']) && !in_array($contain['key'], $contain['fields'])) {
                array_push($contain['fields'], $contain['key']);
            }
            $this->query_active->containMany([is_numeric($alias) ? $contain['table'] : $alias => $contain]);
        }

        return $this;
    }
    
    
    /**
     * Agrega campo adicionales a la consulta
     * @param string $fields Campos a agregar
     */
    public function fields($fields) {
        $this->query_active->fields($fields);
    }
    
    /**
     * Ejecuta una operación de insert o update
     * @return \PowerOn\Database\Model
     */
    public function execute() {
        if ( $this->query_active->getType() == QueryBuilder::SELECT_QUERY ) {
            throw new DataBaseServiceException(sprintf('Este m&eacute;todo es exclusivo de las acciones (%s, %s, %s)', 
                    QueryBuilder::INSERT_QUERY, QueryBuilder::UPDATE_QUERY, QueryBuilder::DELETE_QUERY));
        }
        $query = $this->query_active->getQuery();
        $params = $this->query_active->getParams();
        
        return $this->query($query, $params);
    }
    
    /**
     * Devuelve todos los resultadaos
     * @return QueryResult
     */
    public function all() {
        return $this->query( $this->query_active->getQuery(), $this->query_active->getParams() );
    }

    /**
     * Devuelve el primer resultado
     * @return array
     */
    public function first() {
        $this->limit(1);

        $query = $this->query( $this->query_active->getQuery(), $this->query_active->getParams(), TRUE );
        return $query->toArray();
    }
    
    /**
     *  Devuelve el ultimo resultado
     * @return array
     */
    public function last() {
        $this->query_active->order(['DESC' => 'id']);
        return $this->first();
    }
    
    /**
     * Devulve el campo con ID específico
     * @param integer $id
     * @return array
     */
    public function byId($id) {
        $this->query_active->conditions(['id' => $id]);
        return $this->first();
    }
    
    /**
     * Devuelve la cantidad de resultados encontrados
     * @return integer
     */
    public function count() {
        $this->query_active->fields($this->func()->count());
        $query = $this->query( $this->query_active->getQuery(), $this->query_active->getParams() );
        return $query->count();
    }
    /**
     * Crea una función SQL
     * @param string $function Nombre de la función
     * @param string $params Parámetros
     * @return string
     */
    public function func() {
        if ( $this->functions === NULL ) {
            $this->functions = new Functions();
        }
        
        return $this->functions;
    }
    
    /**
     * Obtiene un resultado por id
     * @param string $table Tabla a obtener resultados
     * @param mix $id Clave primaria id
     * @param array $options [fields, conditions, join, primary_key]
     * @return 
     */
    public function getByIdFrom($table, $id, array $options = []) {
        $cfg = $this->parseOptions($options);
        $cfg['conditions'][$cfg['primary_key'] ?: 'id'] = $id;
   
        $queryModel = $this->configureQueryByOptions($this->select($cfg['fields'])->from($table), $cfg);
        
        return $queryModel->first();
    }
    
    /**
     * Selecciona los datos de una tabla de forma rápida
     * @param string $table Tabla de donde obtener los datos
     * @param array $options Opciones [fields, conditions, join, limit, order]
     * @return QueryResult
     */
    public function selectFrom($table, array $options = []) {
        $cfg = $this->parseOptions($options);
        $queryModel = $this->configureQueryByOptions($this->select($cfg['fields'])->from($table), $cfg);
                      
        return $queryModel->all();
    }
    
    /**
     * Configura la consulta en base a las opciones entregadas
     * @param \PowerOn\Database\Model $queryModel
     * @param array $options Opciones
     * @return \PowerOn\Database\Model
     */
    private function configureQueryByOptions(Model $queryModel, array $options) {
        if ($options['conditions']) {
            $queryModel->where($options['conditions']);
        }
        
        if ($options['join']) {
            $queryModel->join($options['join']);
        }
        
        if ($options['limit']) {
            $queryModel->limit(
                is_array($options['limit']) ? $options['limit'][0] : $options['limit'], 
                is_array($options['limit'] && key_exists(1, $options['limit'])) ? $options['limit'][1] : NULL
            );
        }
        
        if ($options['order']) {
            $queryModel->order($options['order']);
        }
        
        if ($options['containOne']) {
            $queryModel->containOne($options['containOne']);
        }
        
        if ($options['containMany']) {
            $queryModel->containMany($options['containMany']);
        }
        
        return $queryModel;
    }
    
    /**
     * Configura correctamente las opciones enviadas
     * @param array $options
     * @return array
     */
    private function parseOptions(array $options) {
        return $options + [
            'fields' => '*',
            'conditions' => NULL,
            'join' => NULL,
            'limit' => NULL,
            'order' => NULL,
            'primary_key' => NULL,
            'containOne' => [],
            'containMany' => []
        ];
    }
    
    /**
     * Devuelve la consulta configurada por realizar
     * @return string
     */
    public function debug() {
        $args = func_get_args();
        $debug = [];
        
        if ( in_array(self::DEBUG_LAST, $args) ) {
            $debug = end($this->query_log);
        }else if (in_array(self::DEBUG_ACTIVE, $args) ) {
            $debug = $this->query_active;
        } else {
            $debug = $this->query_log;
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
        return $this->query_log;
    }
        
    /**
     * Ejecuta la consulta solicitada
     * 
     * @param string $query La consulta en la base de datos
     * @param array $params Parámetros a incluir en la consulta
     * @return QueryResult|int|bool Devuelve el resultado de la consulta, o FALSE en caso de error
     */
    private function query($query, array $params = [], $unique = FALSE) {
        try {
            $data = $this->service->prepare($query);
            $this->service->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
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

        $result = $this->query_active->getType() == QueryBuilder::INSERT_QUERY
                ? $this->service->lastInsertId() 
                : ($this->query_active->getType() == QueryBuilder::SELECT_QUERY 
                    ? new QueryResult($data, $unique, $this->query_active->getContainsOne(), $this->query_active->getContainMany()) 
                    : $data->rowCount()
                );
        
        if ( $this->query_active->getContainMany() ) {
            $contains = $this->query_active->getContainMany();
            $containResults = [];
            
            foreach ($contains as $alias => $cfg) {
                $ids = $unique ? $result[$cfg['parentKey']] : $result->column($cfg['parentKey']);
                $containResults[$alias] = 
                        $this->configureQueryByOptions(
                            $this
                                ->select($cfg['fields'])
                                ->from($cfg['table'])
                                ->where([$cfg['key'] . ($unique ? '' : ' IN') => $ids]),
                            $cfg
                        )->all()
                    ;
            }
            
            $result->injectContains($containResults);
        }
        
        $this->finalize();
        
        return $result;
    }
}
