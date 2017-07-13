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
 * DataBaseException
 * @author Lucas Sosa
 * @version 0.1
 */
class DataBaseServiceException extends \Exception {
    /**
     * Datos del error PDO
     * @var array
     */
    private $_context = [];
    /**
     * Excepcion de la base de datos
     * @param type $error_message
     */
    public function __construct($error_message, array $context = []) {
        parent::__construct($error_message);
        $this->_context = $context;
    }

    public function getContext() {
        return $this->_context;
    }
}
