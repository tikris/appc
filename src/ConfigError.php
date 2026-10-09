<?php
declare(strict_types=1);

namespace Appc;

/** Erreur de configuration : le message est affiché tel quel (il ne contient aucun secret). */
final class ConfigError extends \RuntimeException
{
}
