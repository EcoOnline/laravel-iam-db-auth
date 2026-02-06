<?php

namespace EcoOnline\DBAuth\Database;

use Illuminate\Database\Connectors\MySqlConnector as DefaultMySqlConnector;
use EcoOnline\DBAuth\Database\Concerns\UsesIamAuthentication;

class MySqlConnector extends DefaultMySqlConnector
{
    use UsesIamAuthentication;
}
