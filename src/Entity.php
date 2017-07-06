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

use function \PowerOn\Application\is_json;
/**
 * Entity
 * @author Lucas Sosa
 * @version 0.1
 */
class Entity {
    /**
     * Los datos de la entidad
     * @var array 
     */
    public $_data = [];
    
    /**
     * Carga la entidad segun campo especificado
     * @param array $data [Opcional] Datos iniciales de la entidad
     */
    public function __construct(array $data = []) {
        $this->fill($data);
        if ( $this->_data ) {
            $this->initialize();
        }
    }
    public function initialize() {}
    
    /**
     * Completas las variables de la clase hija
     * @param array $data La información a completar en la clase hija
     */
    public function fill(array $data) {
        if ( $data ) {
            $this->_data = $data;
            foreach ($data as $name => $value) {
                if ( property_exists($this, $name) ) {
                    $this->{ $name } = is_array($this->{ $name }) ? 
                    ($value && is_json($value) ? json_decode($value, TRUE) : []) : $value;
                }
            }
        }
    }
}
