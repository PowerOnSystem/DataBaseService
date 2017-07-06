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

use function \PowerOn\Application\array_search_full;
use function \PowerOn\Application\json_encode_clean;

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
     * Setea manualmente el nombre de la tabla en caso que no siga las normas estandar de nombramientos del framework
     * @param string $table Nombre real de la tabla en la base de datos
     */
    protected function setTableName($table) {
        $this->_table_name = addslashes($table);
    }
        
    /**
     * Verifica si existe un registro 
     * @param array|integer $conditions Condiciones o el número de ID
     * @return boolean
     */
    public function exist($conditions) { 
        return $this->_model->find($this->_table_name)
                ->where(is_array($conditions ? $conditions : ['id' => $conditions]))->count() ? TRUE : FALSE;
    }
    
    /**
     * Devuelve los resultados encontrados
     * @param string $mode Modo en que se devuelven los resultados
     * @param array $options Opciones de configuración de los resultados
     * @param mix $__   Más parámetros a pasar al procesador de resultados
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

        $this->_model->find($this->_table_name);
        if ($config['fields']) {
            $this->_model->select($config['fields']);
        }
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
        if ($config['contain']) {
            $contain = is_array($config['contain']) ? $config['contain'] : [$config['contain']];
            $joins = [];
            foreach ($contain as $c) {
                if ( !key_exists($c, $this->_joins) ) {
                    throw new DevException(sprintf('La asociación (%s) no fue configurada en la tabla', $c));
                }
                $joins[$c] = $this->_joins[$c];
            }
            $this->_model->join($joins);
            
            $contain_result = array_combine(array_values($contain), array_fill(0, count($contain), '*'));

            $this->_model->pushFields($contain_result);
        }
        $method_mode = 'fetch' . Inflector::classify($mode);

        if ( !method_exists($this, $method_mode) ) {
            throw new DevException(sprintf('El método (%s) para obtener los resultados de la tabla (%s) no fue especificado', 
                    $mode, $this->_table_name), ['method_name' => $method_mode, 'table' => $this]);
        }
        
        $args = array_diff_key($options, $default);
        
        return $this->{ $method_mode } ( $args );
    }
    
    /**
     * Devuelve el primer resultado encontrado
     * @return array
     */
    protected function fetchAll() {
        return $this->_model->all();
    }
    
    /**
     * Devuelve el primer resultado encontrado
     * @return array
     */
    protected function fetchFirst() {
        return $this->_model->first();
    }
    
    /**
     * Devuelve la cantidad de registros encontrados
     * @return integer
     */
    protected function fetchCount() {
        return $this->_model->count();
    }
    
    /**
     * Devuelve los resultados utilizando el campo ID como indice del array
     * @return array
     */
    protected function fetchId() {
        if ( $this->_model->getFields() && !array_search_full($this->_model->getFields(), 'id') ) {
            $this->_model->pushFields('id');
        }

        return $this->_model->allByID();
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
        if ( !$column ) {
            throw new DevException('Debe especificar la columna a obtener, agregue el valor "column" del array de configuraci&oacute;n');
        }
        return $this->_model->column($column);
    }
    
    /**
     * Devuelve el primér resultado único en una celda específica
     * @param array $config Configuración Ejemplo: <code>['field' => 'name']</code>
     * @return string Devuelve el resultado único solicitado o NULL si no existe
     */
    protected function fetchUnique(array $config = []) {
        $field = key_exists('field', $config) ? $config['field'] : NULL;
        
        if ( !$field ) {
            throw new DevException('Debe especificar la celda a obtener, agregue el valor "field" del array de configuraci&oacute;n');
        }
        
        $this->_model->pushFields($field);
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
                throw new DevException('Debe especificar por lo menos el campo a utilizar como valor del array,'
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
            $fields = array($field_key, $field_value);
        }
        $this->_model->pushFields($fields);
        
        return $this->_model->columnCombine($field_value, is_array($field_key) ? reset($field_key) : $field_key);
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
            $data = $this->_model->find($this->_table_name)->select($fields)->id($condition);
        } else if ( is_array($condition) ) {
            $data = $this->_model->find($this->_table_name)->select($fields)->where($condition)->first();
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
        $class_name = 'App\Model\Entities\\' . Inflector::classify(Inflector::singularize($this->_table_name));
        if ( !class_exists($class_name) ) {
            throw new DevException(sprintf('La clase (%s) no existe', $class_name), ['table' => $this->_table_name]);
        }
        
        /* @var $entity Entity */
        $entity = new $class_name();
        $entity->fill($data);
        $entity->initialize();
        
        return $entity;
    }
    
    /**
     * Crea una asociación a una tabla unica, un usuario puede tener asociado un perfil único.
     * <pre>
     * Ejemplo: <code>$table->hasOne('profiles');</code>
     * El resultado SQL resultante:
     * <code>SELECT * FROM users INNER JOIN profiles ON profiles.id = users.id_profile</code>
     * </pre>
     * @param string $table Nombre de la tabla  vincular
     * @param string $field [Opcional] Campo de la base de datos que guarda el ID de la tabla a vincular,
     * por defecto el framework utiliza <i>id_(nombre_tabla_en_singular)</i>, por ejemplo si el nombre de la tabla es
     * profiles, el campo resultante por defecto sería <i>id_profile</i>
     * @param string $join_field [Opcional] El campo identificador de la tabla a vincular, por defecto es <i>id</i>
     */
    protected function hasOne( $table, $field = NULL, $join_field = 'id' ) {
        $link_field = $field ? $field : 'id_' . strtolower(Inflector::singularize($table));
        $this->_joins[$table] = [$join_field => [$link_field, $this->_table_name, '=', 'INNER']];
    }
    
    /**
     * Crea una asociación a una tabla múltiple, un artículo puede contener muchos comentarios.
     * <pre>
     * Ejemplo: <code>$table->hasMany('comments');</code>
     * El resultado SQL resultante:
     * <code>SELECT * FROM articles; SELECT * FROM comments WHERE id_article IN (SELECT id FROM articles);</code>
     * </pre>
     * @param string $table Nombre de la tabla  vincular
     * @param string $field [Opcional] Campo de la base de datos que guarda el ID de la tabla a vincular,
     * por defecto el framework utiliza <i>id_(nombre_tabla_en_singular)</i>, por ejemplo si el nombre de la tabla es
     * profiles, el campo resultante por defecto sería <i>id_profile</i>
     * @param string $join_field [Opcional] El campo identificador de la tabla a vincular, por defecto es <i>id</i>
     */
    protected function hasMany( $table, $field = NULL, $join_field = 'id' ) {
        $link_field = $field ? $field : 'id_' . strtolower(Inflector::singularize($table));
        $this->_joins[$table] = [$join_field => [$link_field]];
    }
    
    /**
     * Crea una asociación a una tabla unica, es lo inverso a hasOne en dirección contraria, perfil único pertenece a un usuario específico.
     * <pre>
     * Ejemplo: <code>$table->belongsTo('users');</code>
     * El resultado SQL resultante:
     * <code>SELECT * FROM profiles LEFT JOIN users ON users.id = profiles.id_user</code>
     * </pre>
     * @param string $table Nombre de la tabla  vincular
     * @param string $field [Opcional] Campo de la base de datos que guarda el ID de la tabla a vincular,
     * por defecto el framework utiliza <i>id_(nombre_tabla_en_singular)</i>, por ejemplo si el nombre de la tabla es
     * profiles, el campo resultante por defecto sería <i>id_profile</i>
     * @param string $join_field [Opcional] El campo identificador de la tabla a vincular, por defecto es <i>id</i>
     */
    protected function belongsTo( $table, $field = NULL, $join_field = 'id') {
        $link_field = $field ? $field : 'id_' . strtolower(Inflector::singularize($table));
        $this->_joins[$table] = [$join_field => [$link_field]];
    }
    
    protected function belongsToMany( $table, $field = NULL, $namespace = NULL, $property = NULL ) {
        $link = $field ? $field : 'id_' . strtolower($table);
        $link_table = $property ? $property : $table;
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
                        $update[$name] = is_array($entity->{ $name }) ? json_encode_clean($entity->{ $name }) : $entity->{ $name };
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
                    $values[$key] = is_array($value) ? json_encode_clean($value) : $value;
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
}
