<?php

namespace Codeception\Module;

use Codeception\Lib\InnerBrowser;
use Codeception\Lib\Interfaces\DependsOnModule;
use Codeception\Module;
use Codeception\TestInterface;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
use OpenAPIValidation\PSR7\Exception\ValidationFailed;
use OpenAPIValidation\PSR7\OperationAddress;
use OpenAPIValidation\PSR7\RequestValidator;
use OpenAPIValidation\PSR7\ResponseValidator;
use OpenAPIValidation\PSR7\ValidatorBuilder;
use Psr\Http\Message\RequestInterface;
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
        'swagger' => '',
    ];

    /**
     * Сообщение при неверной конфигурации.
     *
     * @var string
     */
    protected $dependencyMessage = <<<EOF
Please, add REST and PhpBrowser module in configuration.
--
modules:
    enabled:
        - SwaggerApiValidator:
            depends: [REST, PhpBrowser]
--
EOF;

    /**
     * Настроенный модуль браузера, с которым работает модуль REST.
     *
     * @var InnerBrowser
     */
    protected $innerBrowser;

    /**
     * Настроенный модуль REST.
     *
     * @var REST
     */
    protected $rest;

    /**
     * Сообщение, с которым валится тест.
     *
     * @var string
     */
    protected $errorMessage = '';

    /**
     * Путь до файла со сваггер-описанием.
     *
     * @var string
     */
    protected $swaggerFile = '';

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
        return [
            REST::class       => $this->dependencyMessage,
            PhpBrowser::class => $this->dependencyMessage,
        ];
    }

    /**
     * Устанавливаем ссылки на модули, с которыми будем работать.
     *
     * @param REST         $rest
     * @param InnerBrowser $innerBrowser
     */
    public function _inject(REST $rest, InnerBrowser $innerBrowser)
    {
        $this->rest         = $rest;
        $this->innerBrowser = $innerBrowser;
        $this->setSwaggerFile($this->config['swagger']);
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
     * Получаем отправленный запрос в формате PSR7.
     *
     * @return
     */
    public function getPSR7Request(): RequestInterface
    {
        $internalRequest = $this->getRequest();
        $headers         = $this->innerBrowser->headers;
        return new Psr7Request($internalRequest->getMethod(), $internalRequest->getUri(), $headers, $internalRequest->getContent());
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
     * Получаем ответ на запрос в формате PSR7.
     *
     * @return Psr7Response
     */
    public function getPsr7Response(): Psr7Response
    {
        $internalResponse = $this->getResponse();
        return new Psr7Response($internalResponse->getStatus(), $internalResponse->getHeaders(), $internalResponse->getContent());
    }

    /**
     * Получаем объект валидатора, описанный в swagger-файле.
     *
     * @return RequestValidator
     */
    public function getRequestValidator(): RequestValidator
    {
        return ( new ValidatorBuilder )->fromYamlFile($this->getSwaggerFile())->getRequestValidator();
    }

    /**
     * Получаем объект валидатора, описанный в swagger-файле.
     *
     * @return ResponseValidator
     */
    public function getResponseValidator(): ResponseValidator
    {
        return ( new ValidatorBuilder )->fromYamlFile($this->getSwaggerFile())->getResponseValidator();
    }

    public function validateRequest()
    {
        $validator = $this->getRequestValidator();
        $request   = $this->getPSR7Request();
        try {
            $validator->validate($request);
        } catch (ValidationFailed $e) {
            $this->errorMessage = $e->getMessage();
            return false;
        }
        return true;
    }

    /**
     * Метод для проверки запроса на валидность в соответствии со сваггер-файлом.
     */
    public function seeRequestIsValid()
    {
        $this->assertTrue($this->validateRequest(), $this->errorMessage);
    }

    public function validateResponse()
    {
        $validator = $this->getResponseValidator();
        $request   = $this->getPSR7Request();
        $response  = $this->getPSR7Response();
        $operation = new OperationAddress($this->getPathPattern($request), strtolower($request->getMethod()));
        try {
            $validator->validate($operation, $response);
        } catch (ValidationFailed $e) {
            $this->errorMessage = $e->getMessage();
            return false;
        }
        return true;
    }

    /**
     * Метод для проверки ответа на валидность в соответствии со сваггер-файлом.
     */
    public function seeResponseIsValid()
    {
        $this->assertTrue($this->validateResponse(), $this->errorMessage);
    }

    /**
     * Метод для получения ссылки на сваггер-файл.
     *
     * @return string
     */
    public function getSwaggerFile(): string
    {
        return $this->swaggerFile;
    }

    /**
     * Метод для установки ссылки на сваггер-файл.
     *
     * @param string $swaggerFile
     */
    public function setSwaggerFile(string $swaggerFile)
    {
        $this->swaggerFile = codecept_root_dir($swaggerFile);
    }

    /**
     * Метод для получения шаблона пути.
     * TODO сделать нормально, пока костыль.
     *
     * @param RequestInterface $request
     *
     * @return string
     */
    public function getPathPattern(RequestInterface $request): string
    {
        return preg_replace('/\/[0-9]+/', '/{id}', $request->getUri()->getPath());
    }
}