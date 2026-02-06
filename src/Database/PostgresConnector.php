<?php

namespace EcoOnline\DBAuth\Database;

use Illuminate\Database\Connectors\PostgresConnector as DefaultPostgresConnector;
use EcoOnline\DBAuth\Database\Concerns\UsesIamAuthentication;

class PostgresConnector extends DefaultPostgresConnector
{
    use UsesIamAuthentication;
}
