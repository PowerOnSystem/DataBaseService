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
class Query implements \Iterator {
    private $_results = [];
    /**
     * Resultado de una consulta para iterar con ella
     * @var \mysqli_result
     */
    private $_mysqli_result = NULL;
    
    public function __construct(\mysqli_result $result) {
        $this->_mysqli_result = $result;
    }

    public function current() {
        return current($this->_results);
    }

    public function key() {
        
    }

    public function next() {
        
    }

    public function rewind() {
        if ( !$this->_results ) {
            $this->toArray();
        }
        reset($this->_results);
    }

    public function valid() {
        
    }

    public function toArray() {
        $this->_results = $this->_mysqli_result->fetch_array();
        return $this->_results;
    }
}
