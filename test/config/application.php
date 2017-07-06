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

/*
 * Configuracion de la aplicación completa y los servicios instalados.
 * En esta sección se configuran todos los servicios utilizados.
 * Por ejemplo, si se está utilizando DataBaseService:
 * return [
 *      'DataBaseService' => [
 *          'host' => 'localhost',
 *          'user' => ...
 *      ]
 * ]
 */
return [
    'Global' => [
        'lang' => 'es'
    ],
    'Date' => [
        'date' => 'd/m/Y',
        'date_time' => 'd/m/Y H:i',
        'time' => 'H:i'
    ],
    'Webmaster' => [
        'name' => 'Lucas S. Sosa',
        'email' => 'sosalucas87@gmail.com'
    ],
    'DataBaseService' => [
        'host' => 'localhost',
        'user' => 'root',
        'password' => '',
        'database' => 'poweron', 
        'port' => '3307'
    ]
];