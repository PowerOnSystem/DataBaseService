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
 * Table
 * @author Lucas Sosa
 * @version 0.1
 */
class Table {
    /**
     * Asociaciones de la tabla
     * @var array
     */
    private $_joins = [];
    /**
     * Servicio de base de datos msyql
     * @var Model
     */
    protected $_model = NULL;
    /**
     * Nombre de la tabla
     * @var string
     */
    private $_table_name = NULL;
    
    public function __construct(Model $connect) {
        $class = explode('\\', get_called_class());
        $this->_table_name = strtolower(array_pop($class));
        $this->_model = $connect;
    }
    
    /**
     * Inicialización de una tabla, en este método se incluyen las configuraciones pertenecientes a la tabla hija
     */
    public function initialize() {}
        
    /**
     * Verifica si existe un registro 
     * @param array|integer $conditions Condiciones o el número de ID
     * @return boolean
     */
    public function exist($conditions) { 
        return $this->_model->select()->from($this->_table_name)
            ->where(is_array($conditions ? $conditions : ['id' => $conditions]))->count() ? TRUE : FALSE;
    }
    
    /**
     * Obtiene la entidad vinculada con la tabla
     * @param array|integer $condition Condiciones de la entidad o el ID
     * @param array|string $fields [Opcional] Campos a cargar en la entidad
     * @return Entity
     */
    public function get($condition = [], $fields = []) {
        $data = [];
        if ( is_numeric($condition) ) {
            $data = $this->_model->select($fields)->from($this->_table_name)->id($condition)->toArray();
        } else if ( is_array($condition) ) {
            $data = $this->_model->select($fields)->from($this->_table_name)->where($condition)->first()->toArray();
        }
        
        return $this->newEntity($data ? $data : []);
    }
    
    /**
     * Obtiene la entidad vinculada con la tabla
     * @param array|integer $conditions Condiciones de la entidad o el ID
     * @param array|string $fields [Opcional] Campos a cargar en la entidad
     * @return Entity
     */
    public function newEntity(array $data = []) {
        $reflection = new \ReflectionClass($this);
        $table_namespace = explode('\\', $reflection->getNamespaceName());
        array_pop($table_namespace);
        $namespace = implode('\\', $table_namespace);
        
        $class_name = $namespace . '\Entities\\' . Inflector::classify(Inflector::singularize($this->_table_name));
        if ( !class_exists($class_name) ) {
            throw new DataBaseServiceException(sprintf('La clase (%s) no existe', $class_name), ['table' => $this->_table_name]);
        }
        
        /* @var $entity Entity */
        $entity = new $class_name();
        $entity->fill($data);
        $entity->initialize();
        
        return $entity;
    }
    
    /**
     * Devuelve los resultados encontrados
     * @param string $mode Modo en que se devuelven los resultados
     * @param array $options Opciones de configuración de los resultados
     * <pre>
     * Las opciones son:<ul>
     *  <li><i>fields</i>: Campos a seleccionar</li>
     *  <li><i>conditions</i>: Condiciones de la consulta</li>
     *  <li><i>contain</i>: Asociaciones preconfiguradas a cargar</li>
     *  <li><i>limit</i>: Limite de la consulta</li>
     *  <li><i>join</i>: Asociaciones personalizadas</li>
     *  <li><i>order</i>: Orden de los resultados</li>
     * </ul>
     * </pre>
     * @return array
     */
    public function fetch($mode = 'all', array $options = []) {
        $default = [
            'fields' => [],
            'conditions' => [],
            'contain' => NULL,
            'limit' => NULL,
            'join' => [],
            'order' => []
            
        ];
        $config = $options + $default;

        $this->_model->select($config['fields'])->from($this->_table_name);

        if ($config['join']) {
            $this->_model->join($config['join']);
        }
        if ($config['conditions']) {
            $this->_model->where($config['conditions']);
        }
        if ($config['order']) {
            $this->_model->order($config['order']);
        }
        if ($config['limit']) {
            $this->_model->limit($config['limit']);
        }
        
        $method_mode = 'fetch' . Inflector::classify($mode);

        if ( !method_exists($this, $method_mode) ) {
            throw new DataBaseServiceException(sprintf('El método (%s) para obtener los resultados de la tabla (%s) no fue especificado', 
                    $mode, $this->_table_name), ['method_name' => $method_mode, 'table' => $this]);
        }
        
        $args = array_diff_key($options, $default);
        
        $results = $this->{ $method_mode } ( $args );

        if ($config['contain']) {
            $results = $this->proccessContain(is_array($config['contain']) ? $config['contain'] : [$config['contain']], $results);
        }
        
        return $results;
    }
    
    /**
     * Agrega o modifica una entidad solicitada
     * @param \PowerOn\Database\Entity $entity Entidad a guardar
     * @return boolean
     */
    public function save(Entity $entity) {
        if ( $entity->id ) {
            $update = [];
            if ( $entity->_data ) {
                foreach ($entity->_data as $name => $value) {
                    if ( property_exists($entity, $name) && $value != $entity->{ $name } ) {
                        $update[$name] = is_array($entity->{ $name }) ? json_encode($entity->{ $name }) : $entity->{ $name };
                    }
                }
            }
            
            if ( $update ) {
                return  $this->_model->update($this->_table_name)->set($update)->where(['id' => $entity->id])->execute();
            }

            return TRUE;
        } else {
            $properties = get_object_vars($entity);
            $values = [];
            foreach ($properties as $key => $value) {
                if ( $value !== NULL && !(is_array($value) && !$value) && substr($key, 0, 1) != '_' ) {
                    $values[$key] = is_array($value) ? json_encode($value) : $value;
                }
            }

            if ($values) {
                $entity->id = $this->_model->insert($this->_table_name)->values($values)->execute();
                return $entity->id;
            }
        }
                
        return FALSE;
    }
    
    /**
     * Elimina la entidad de la base de datos
     * @param \PowerOn\Database\Entity $entity La entidad a eliminar
     * @return boolean
     */
    public function delete(Entity $entity) {
        if ( $entity->id ) {
            return $this->_model->delete($this->_table_name)->where(['id' => $entity->id])->execute();
        }
        
        return FALSE;
    }
    
    public function model() {
        return $this->_model;
    }
    
    /**
     * Setea manualmente el nombre de la tabla en caso que no siga las normas estandar de nombramientos del framework
     * @param string $table Nombre real de la tabla en la base de datos
     */
    protected function setTableName($table) {
        $this->_table_name = addslashes($table);
    }
    
    /**
     * Devuelve el primer resultado encontrado
     * @return array
     */
    protected function fetchAll() {
        return $this->_model->all()->toArray();
    }
    
    /**
     * Devuelve el primer resultado encontrado
     * @return array
     */
    protected function fetchFirst() {
        return $this->_model->first()->toArray();
    }
    
    /**
     * Devuelve la cantidad de registros encontrados
     * @return integer
     */
    protected function fetchCount() {
        return $this->_model->count();
    }
    
    /**
     * Devuelve los resultados especificando una columna única y eliminando elementos repetidos
     * @param array $config Configuración Ejemplo: <code>['column' => 'name']</code>
     * @return array
     */
    protected function fetchColumnUnique(array $config = []) {
        return array_unique($this->fetchColumn($config));
    }
    
    /**
     * Devuelve los resultados especificando una columna única
     * @param array $config Configuracion Ejemplo: <code>['column' => 'name']</code>
     * @return array
     */
    protected function fetchColumn(array $config = []) {
        $column = key_exists('column', $config) ? $config['column'] : NULL;
        $this->_model->fields([$column]);

        if ( !$column ) {
            throw new DataBaseServiceException('Debe especificar la columna a obtener, agregue el valor'
                    . ' "column" del array de configuraci&oacute;n');
        }
        return $this->_model->all()->column($column);
    }
    
    /**
     * Devuelve el primer resultado único en una celda específica
     * @param array $config Configuración Ejemplo: <code>['field' => 'name']</code>
     * @return string Devuelve el resultado único solicitado o NULL si no existe
     */
    protected function fetchUnique(array $config = []) {
        $field = key_exists('field', $config) ? $config['field'] : NULL;
        
        if ( !$field ) {
            throw new DataBaseServiceException('Debe especificar la celda a obtener, agregue el valor "field" del array de configuraci&oacute;n');
        }
        
        $this->_model->fields($field);
        $data = $this->fetchFirst();
        
        return key_exists($field, $data) ? $data[$field] : NULL;
    }
    
    /**
     * Devuelve los resultados combinando un campo para la clave y otro para el valor
     * @param array $config Configuracion Ejemplo: <code>['fieldKey' => 'id', 'fieldValue' => 'name']</code>, 
     * por defecto 'fieldKey' es <i>id</i>
     * @return array
     */
    protected function fetchCombine(array $config = []) {
        $field_value = key_exists('fieldValue', $config) ? $config['fieldValue'] : NULL;
        
        if (!$field_value) {
                throw new DataBaseServiceException('Debe especificar por lo menos el campo a utilizar como valor del array,'
                    . ' agregue el valor "fieldValue" al array de configuraci&oacute;n');
        }
        
        $field_key = key_exists('fieldKey', $config) ? $config['fieldKey'] : 'id';
        
        if ( is_array($field_value) ) {
            $fields = $field_value;
            if ( is_array($field_key) ) { 
                $fields[key($field_key)] = [reset($field_key)];
            } else {
                array_push($fields, $field_key);
            }
            
        } else {
            $fields = [$field_key, $field_value];
        }
        $this->_model->fields($fields);
        
        return $this->_model->all()->combine($field_value, is_array($field_key) ? reset($field_key) : $field_key);
    }
    
    /**
     * Crea una asociación a una tabla unica, un usuario puede tener asociado un perfil único.
     * <pre>
     * Ejemplo: <code>$table->hasOne('profiles');</code>
     * El resultado SQL resultante:
     * <code>SELECT * FROM users INNER JOIN profiles ON profiles.id = users.id_profile</code>
     * Y la asociación sería: 
     * <code>['users' => [0 => ['name' => 'user1', 'profile' => [...], 1 => [...]] </code>
     * </pre>
     * @param string $table Nombre de la tabla  vincular
     * @param string $field [Opcional] Campo de la base de datos que guarda el ID de la tabla a vincular,
     * por defecto el framework utiliza <i>id_(nombre_tabla_en_singular)</i>, por ejemplo si el nombre de la tabla es
     * profiles, el campo resultante por defecto sería <i>id_profile</i>
     * @param string $join_field [Opcional] El campo identificador de la tabla a vincular, por defecto es <i>id</i>
     */
    protected function hasOne( $table, $field = NULL, $join_field = 'id' ) {
        $this->_joins[$table] = [
            'mode' => 'hasOne', 
            'field' => $field, 
            'join_field' => $join_field
        ];
    }
    
    /**
     * Crea una asociación a una tabla múltiple, un artículo puede contener muchos comentarios.
     * <pre>
     * Ejemplo: <code>$table->hasMany('comments');</code>
     * El resultado SQL resultante:
     * <code>SELECT * FROM articles; SELECT * FROM comments WHERE id_article IN (SELECT id FROM articles);</code>
     * 
     * Y la asociación sería: 
     * <code>['articles' => [0 => ['title' => 'art1', 'comments' => [...], 1 => [...]] </code>
     * </pre>
     * @param string $table Nombre de la tabla  vincular
     * @param string $field [Opcional] Campo de la base de datos que guarda el ID de la tabla a vincular,
     * por defecto el framework utiliza <i>id_(nombre_tabla_en_singular)</i>, por ejemplo si el nombre de la tabla es
     * profiles, el campo resultante por defecto sería <i>id_profile</i>
     * @param string $join_field [Opcional] El campo identificador de la tabla a vincular, por defecto es <i>id</i>
     */
    protected function hasMany( $table, $field = NULL, $join_field = 'id' ) {
        $this->_joins[$table] = [
            'mode' => 'hasMany', 
            'field' => $field, 
            'join_field' => $join_field
        ];
    }
    
    /**
     * Crea una asociación a una tabla unica, es lo inverso a hasOne en dirección contraria, perfil único pertenece a un usuario específico.
     * <pre>
     * Ejemplo: <code>$table->belongsTo('users');</code>
     * El resultado SQL resultante:
     * <code>SELECT * FROM profiles LEFT JOIN users ON users.id = profiles.id_user</code>
     * 
     * Y la asociación sería: 
     * <code>['profiles' => [0 => ['gender' => 'male', 'user' => [...], 1 => [...]] </code>
     * </pre>
     * @param string $table Nombre de la tabla  vincular
     * @param string $field [Opcional] Campo de la base de datos que guarda el ID de la tabla a vincular,
     * por defecto el framework utiliza <i>id_(nombre_tabla_en_singular)</i>, por ejemplo si el nombre de la tabla es
     * profiles, el campo resultante por defecto sería <i>id_profile</i>
     * @param string $join_field [Opcional] El campo identificador de la tabla a vincular, por defecto es <i>id</i>
    */
    protected function belongsTo( $table, $field = NULL, $join_field = 'id' ) {
        $this->_joins[$table] = [
            'mode' => 'belongsTo', 
            'field' => $field, 
            'join_field' => $join_field
        ];
    }
    
    /**
     * Procesa las asocianciones configuradas en la tabla actual
     * @param array $contain Las asociaciones solicitadas
     * @param array $results Los resultados devueltos de la consulta principal
     * @return array Devuelve un array con las asociaciones agregadas
     * @throws DataBaseServiceException
     */
    private function proccessContain(array $contain, array $results = []) {
        $new_results = $results;
        foreach ($contain as $table) {
            if ( !key_exists($table, $this->_joins) ) {
                throw new DataBaseServiceException(sprintf('La asociación (%s) no fue configurada en la tabla', $table));
            }
            $data = $this->_joins[$table];
            switch ($data['mode']) {
                case 'hasOne':
                    $table_singular = strtolower(Inflector::singularize($table));
                    $field = $data['field'] ? $data['field'] : 'id_' . strtolower(Inflector::singularize($this->_table_name));
                    $join_field = $data['join_field'] ? $data['join_field'] : 'id';
                    foreach ($results as $key => $result) {
                        $new_results[$key][$table_singular] = 
                                $this->_model->select('all')->from($table)->where([$field => $result[$join_field]])->first()->toArray();
                    }
                    break;
                case 'hasMany':
                    $field = $data['field'] ? $data['field'] : 'id_' . strtolower(Inflector::singularize($this->_table_name));
                    $join_field = $data['join_field'] ? $data['join_field'] : 'id';
                    foreach ($results as $key => $result) {
                        $new_results[$key][$table] =
                                $this->_model->select('all')->from($table)->where([$field => $result[$join_field]])->all()->toArray();
                    }
                    break;
                case 'belongsTo':
                    $table_singular = strtolower(Inflector::singularize($table));
                    $field = $data['field'] ? $data['field'] : 'id_' . $table_singular;
                    $join_field = $data['join_field'] ? $data['join_field'] : 'id';
                    foreach ($results as $key => $result) {
                        $new_results[$key][$table_singular] = $result[$field] ? 
                                $this->_model->select('all')->from($table)->where([$join_field => $result[$field]])->first()->toArray() : [];
                    }
                    break;
                default:
                    break;
            }
        }
        return $new_results;
    }
}
