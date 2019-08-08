# PHP Terminal Chatroom

Oh No! Not another terminal chatroom! Oh yes boys and girls!

This is an experimental project showcasing how PHP can be used as a standalone terminal chat room. The idea of this project is to show how PHP can handle multiple clients using the low level TCP socket connections.

## Prerequisites

You will need at least PHP 7.1.0. No web server is needed. Your PHP must be compliled with socket support.

## Getting Started

You may configure the port that you want to bind the chat server to in the config.php file:

```
<?php

$server_port = 9050;
```

Next, in your terminal, simply type the following command to start the chat server.


```sh
$ php server.php
```

Clients can use telnet to connect to the chat room:


```sh
$ telnet 127.0.0.1 9050
```

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details


