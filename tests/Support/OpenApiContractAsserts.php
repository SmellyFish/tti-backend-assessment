<?php

namespace Tests\Support;

use Illuminate\Testing\TestResponse;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

trait OpenApiContractAsserts
{
    private static ?ResponseValidator $openApiResponseValidator = null;

    protected function assertMatchesOpenApiContract(
        TestResponse $response,
        string $method,
        string $pathTemplate
    ): void {
        $validator = self::$openApiResponseValidator ??= (new ValidatorBuilder)
            ->fromYamlFile(base_path('resources/docs/openapi.yaml'))
            ->getResponseValidator();

        $httpResponse = $response->baseResponse;

        $psrResponse = new Response(
            $httpResponse->getStatusCode(),
            ['Content-Type' => 'application/json'],
            Stream::create($this->responseContentAsString($httpResponse)),
        );

        $validator->validate(
            new OperationAddress($pathTemplate, strtolower($method)),
            $psrResponse,
        );
    }

    private function responseContentAsString(SymfonyResponse $response): string
    {
        $content = $response->getContent();

        return is_string($content) ? $content : '';
    }
}
