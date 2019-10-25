<?php

namespace Codeception\Module;

use Codeception\Lib\InnerBrowser;
use Codeception\Lib\Interfaces\DependsOnModule;
use Codeception\Module;
use Garden\Schema\RefNotFoundException;
use Garden\Schema\Schema;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response;

class SwaggerApiValidator extends Module implements DependsOnModule
{
    /**
     * Конфигурация модуля.
     *
     * @var array
     */
    protected $config = [
        'swagger' => ''
    ];

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
     * @var InnerBrowser
     */
    protected $innerBrowser;

    /**
     * @var REST
     */
    protected $rest;

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

    /**
     * Устанавливаем ссылки на модули, с которыми будем работать.
     *
     * @param REST         $rest
     * @param InnerBrowser $innerBrowser
     */
    public function _inject(REST $rest, InnerBrowser $innerBrowser)
    {
        $this->rest = $rest;
        $this->innerBrowser = $innerBrowser;
    }

    /**
     * Получаем отправленный запрос в формате BrowserKit.
     *
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->rest->client->getInternalRequest();
    }

    /**
     * Получаем ответ на запрос в формате BrowserKit.
     *
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->rest->client->getInternalResponse();
    }

    /**
     * Получаем объект схемы, описанной в swagger-файле.
     *
     * @return Schema
     */
    public function getSchema()
    {
        return Schema::parse(yaml_parse_file($this->config['swagger']));
    }

    public function seeRequestIsValid()
    {
        $schema = $this->getSchema();
        $request = $this->getRequest();
        try {
            $result = $schema->isValid($request, ['request' => true]);
        } catch (RefNotFoundException $e) {
            $result = false;
        }
        return $result;
    }
}