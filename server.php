<?php
/**
 * PHP Terminal Chatroom
 *
 * OH NO! NOT ANOTHER TERMINAL CHATROOM! This is a pet project using socket library.
 * All features are experimental and it is not meant for commercial purposes.
 *
 * PHP version 7.1.0
 *
 * MIT License
 * 
 * Copyright (c) 2019 Jiacheng Wu
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * 
 * @author     Jiacheng Wu <jia.cheng.wu@gmail.com>
 * @copyright  2019
 * @license    MIT License
 * @link       https://github.com/jasonwubz/php-terminal-chatroom
 * 
 */

set_time_limit(0);

require "./config.php";

function HandleServerSocket(&$server_socket) 
{
    global $clients;
    global $broadcast_queue;

    // a new client! add to $clients array and respond back
    $new_client = socket_accept($server_socket);

    if ($new_client === false) {
        // TODO:
        sleep(30);
        return;
    }
    $clients[] = $new_client;

    echo date('H:i:s') . ' Client pool size is: ' . count($clients) . PHP_EOL;

    // send the client a welcome message
    socket_write(
        $new_client, 
        "\33[100m OH NO! NOT ANOTHER TERMINAL CHAT ROOM! \33[0m" . PHP_EOL .
        "Welcome! There are \33[44m " . (count($clients) - 1) . " \33[0m client(s) connected. " . PHP_EOL .
        "Say something! Type 'quit' to end chat." . 
        PHP_EOL . 
        PHP_EOL
    );

    $client_ip = '';
    $client_port = 0;

    socket_getpeername($new_client, $client_ip, $client_port);

    echo date('H:i:s') . " New client connected \33[44m[{$client_ip}:{$client_port}]\33[0m" . PHP_EOL;
    
    // adds announcement to queue so that server will send to everyone
    $new_key = (integer) count($broadcast_queue) + 1;
    $broadcast_queue = [
        $new_key => [
            "message" => "New client connected {$client_port}",
            "who" => "SERVER",
            "who_socket" => $new_client
        ]
    ] + $broadcast_queue;

    return;
}

function HandleClientSocket(&$read_socket) 
{
    global $clients;
    global $broadcast_queue;

    // try to peek at client
    $buffer = '';
    $peek_result = socket_recv($read_socket, $buffer, 1024, MSG_PEEK);

    if ($peek_result === false) {
        return;
    }
    
    // if there is some buffer, we want to at least let the server know
    if (strlen($buffer) > 0) {
        if (strlen($buffer) == 1 && $buffer == "\n") {
            return;
        }
        if (strpos($buffer, "\n") === 0) {
            $buffer = substr($buffer, 1, strlen($buffer));
        }
        $client_ip = '';
        $client_port = 0;
        socket_getpeername($read_socket, $client_ip, $client_port);

        //TODO: future development, store $buffer into temp array, concat with existing buffer
        echo "\33[2K\r {$client_port} is typing something...";
    }

    if ($peek_result && (strpos($buffer, "\n") === false && strlen($buffer) < 1024)) {
        // not ready to read
        return;
    }

    // read until newline (due to PHP_NORMAL_READ that has higher preference) or 1024 bytes
    $data = @socket_read($read_socket, 1024, PHP_NORMAL_READ);

    $key = array_search($read_socket, $clients);
    $client_ip = '';
    $client_port = 0;
    socket_getpeername($clients[$key], $client_ip, $client_port);
    
    // if client disconnected, remove client from array
    if ($data === false) {
        unset($clients[$key]);
        echo date('H:i:s') . " client disconnected [{$client_ip}:{$client_port}]" . PHP_EOL;
        return;
    }

    $data = trim($data);

    // check if there is any data after trimming off the spaces
    if (!empty($data)) {

        if (strcasecmp($data, "quit") === 0) {
            //use sends quit command, sad to see them go
            socket_write(
                $read_socket, 
                PHP_EOL .
                "\33[44mconnection terminated, we are sad to see you go...\33[0m" . 
                PHP_EOL
            );
            
            // disconnect and remove from clients then send broadcast
            socket_close($read_socket);
            unset($clients[$key]);

            $new_key = (integer) count($broadcast_queue) + 1;
            $broadcast_queue = [
                $new_key => [
                    "message" => "{$client_port} has left the chat :(",
                    "who" => "SERVER",
                    "who_socket" => $read_socket
                ]
            ] + $broadcast_queue;
            
            return;
        }        
        
        echo PHP_EOL . date('H:i:s') . " [{$client_ip}:{$client_port}] says: {$data}" . PHP_EOL;

        // stores message to queue to be broadcasted
        //array_unshift($broadcast_queue, $data);
        $new_key = (integer) count($broadcast_queue) + 1;
        $broadcast_queue = [
            $new_key => [
                "message" => $data,
                "who" => $client_port,
                "who_socket" => $read_socket
            ]
        ] + $broadcast_queue;
    }
}

function BroadcastMessage(&$write_socket, $message, $who_said, &$who_socket) {
    if ($message === 0xff) {
        if ($who_socket !== $write_socket) {
            socket_write($write_socket, "\33[2K\r {$who_said} is typing...");
        }
    } else {
        if ($who_socket !== $write_socket) {
            socket_write($write_socket, date("H:i:s") . " \33[41m{$who_said}\33[0m said: {$message}" . PHP_EOL);
        }
    }
}

if (function_exists('socket_create') === false) {
    exit("\33[0mfatal: socket functions are not compiled, cannot continue\33[0m" . PHP_EOL);
}

if (function_exists('xdebug_is_enabled') && xdebug_is_enabled()) {
    echo 'warning: XDEBUG is enabled, disable for better performance' .  PHP_EOL;
}

// create a streaming socket of type TCP/IP
$server_socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

if ($server_socket === false) {
    $errorcode = socket_last_error();
    $errormsg = socket_strerror($errorcode);
    exit("\33[41mfatal: unable to create socket: [$errorcode] {$errormsg}\33[0m" . PHP_EOL);
}

// set the option to reuse the port
socket_set_option($server_socket, SOL_SOCKET, SO_REUSEADDR, 1);

// binds the socket address 0 on port $server_port
if (!socket_bind($server_socket, 0, $server_port)) {
    exit("\33[41mfatal: unable to bind socket\33[0m" . PHP_EOL);
}

// start listen for connections
socket_listen($server_socket, SOMAXCONN);

// we want to follow official recommendations to avoid timeouts and non-blocking behavior
//socket_set_block($server_socket);

// stores all the clients in this array
$clients = [];
global $clients;

// stores any message for broadcasting, first in first out FIFO
$broadcast_queue = [
    0 => [
    "message" => "You are the first person! \33[5mCongrats\33[0m",
    "who" => "SERVER",
    "who_socket" => '',
    ]
];
global $broadcast_queue;

echo "server started on port:{$server_port}" . PHP_EOL;

while (true) {

    $write_sockets  = [];
    $except_sockets = [];

    // create a copy, so $server_socket doesn't get modified by socket_select()
    $read_sockets = [$server_socket];
    
    array_push($write_sockets, ...$clients);
    array_push($except_sockets, ...$clients);
    
    if (!empty($clients)) {
        array_push($read_sockets, ...$clients);
    }

    if (empty($clients)) {
        $write_sockets = null;
    }


    if (empty($clients)) {
        $except_sockets = null;
    }

    //echo count($read_sockets)."|";

    // TODO: get this to work without timeout
    $select_result = @socket_select($read_sockets, $write_sockets, $except_sockets, 0);

    if ($select_result < 1) {
        
        $errno = socket_last_error($server_socket);
        if ($errno !== 0) {
            echo socket_strerror($errno) . PHP_EOL;
        }

        // if we were using timeouts and non-blocking mode, sleep would reduce resource consumption
        sleep(1);
    } else {
       
        if (is_array($read_sockets) && count($read_sockets)) {
            foreach ($read_sockets as &$read_socket) {
                if ($read_socket === $server_socket) {
                    // it's the server, check for new client
                    HandleServerSocket($read_socket);
                } else {
                    HandleClientSocket($read_socket);
                }
            }
        }

        if (is_array($write_sockets) && count($write_sockets)) {
            if (is_array($broadcast_queue) && count($broadcast_queue)) {
                $message = array_shift($broadcast_queue);

                foreach ($write_sockets as $w_key => &$w_socket) {
                    BroadcastMessage($w_socket, $message['message'], $message['who'], $message['who_socket']);
                }
            }
        }

        if (is_array($except_sockets) && count($except_sockets)) {
            foreach ($except_sockets as $e_key => &$e_socket) {
                //TODO: handle exceptions
            }
        }

    }
    
    unset($read_sockets);
    unset($write_sockets);
    unset($except_sockets);
    unset($select_result);

    socket_clear_error();    
}
