<?php

namespace Codeception\Module;

use Codeception\Lib\Interfaces\DependsOnModule;
use Codeception\Module;

class SwaggerApiValidator extends Module implements DependsOnModule
{
    /**
     * Сообщение при неверной конфигурации.
     *
     * @var string
     */
    protected $dependencyMessage = <<<EOF
Please, add REST module in configuration.
--
modules:
    enabled:
        - SwaggerApiValidator:
            depends: [REST]
--
EOF;

    /**
     * Specifies class or module which is required for current one.
     *
     * THis method should return array with key as class name and value as error message
     * [className => errorMessage]
     *
     * @return array
     */
    public function _depends(): array
    {
        return [REST::class => $this->dependencyMessage];
    }
}