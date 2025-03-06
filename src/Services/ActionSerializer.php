<?php
namespace AssistantEngine\OpenFunctions\Actions\Services;

use AssistantEngine\OpenFunctions\Core\Contracts\AbstractOpenFunction;

class ActionSerializer
{
    /**
     * The title of the API.
     *
     * @var string
     */
    protected $title;

    /**
     * The version of the API.
     *
     * @var string
     */
    protected $version;

    /**
     * The server URL for the API.
     *
     * @var string
     */
    protected $serverUrl;

    /**
     * An identifier added to each endpoint path.
     *
     * @var string
     */
    protected $identifier;

    /**
     * Holds the generated API paths.
     *
     * @var array
     */
    protected $paths = [];

    /**
     * Holds the generated component schemas.
     *
     * @var array
     */
    protected $componentsSchemas = [];

    /**
     * Constructor.
     *
     * @param string      $title      API title.
     * @param string      $version    API version.
     * @param string      $serverUrl  API server URL.
     * @param string|null $identifier Identifier to be added to each endpoint path.
     */
    public function __construct(string $title = 'Action API', string $version = '1.0.0', string $serverUrl = 'https://api.example.com', ?string $identifier = null)
    {
        $this->title      = $title;
        $this->version    = $version;
        $this->serverUrl  = $serverUrl;
        $this->identifier = $identifier ?? 'default'; // use a default identifier if none is provided
    }

    /**
     * Converts an OpenFunction instance to an action API specification.
     *
     * @param AbstractOpenFunction $action The open function instance.
     *
     * @return array The generated API specification.
     */
    public function serialize(AbstractOpenFunction $action): array
    {
        $definitions = $action->generateFunctionDefinitions();

        // Reset paths and component schemas.
        $this->paths = [];
        $this->componentsSchemas = [];

        foreach ($definitions as $def) {
            $func = $def['function'];
            $functionName = $func['name'];
            $description  = $func['description'] ?? '';

            // Register the parameter schema as a component if provided.
            if (isset($func['parameters'])) {
                $schemaName = $functionName . 'Request';
                $this->componentsSchemas[$schemaName] = $func['parameters'];
            } else {
                $schemaName = null;
            }

            // Build the base endpoint definition.
            $endpoint = [
                'summary'     => $description,
                'operationId' => $functionName,
                'x-openai-isConsequential' => false,
                'responses'   => [
                    '200' => [
                        'description' => 'Successful response',
                        'content'     => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/Response'
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            // Only add a requestBody if a schema is provided.
            if ($schemaName !== null) {
                $endpoint['requestBody'] = [
                    'required' => true,
                    'content'  => [
                        'application/json' => [
                            'schema' => ['$ref' => "#/components/schemas/{$schemaName}"]
                        ]
                    ]
                ];
            }

            // Use the identifier in the endpoint path: /actions/{identifier}/{functionName}
            $this->paths["/actions/{$this->identifier}/{$functionName}"] = [
                'post' => $endpoint
            ];
        }

        // Define the response schema as a reusable component.
        $this->componentsSchemas['Response'] = [
            'type' => 'object',
            'properties' => [
                'isError' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Indicates if the response represents an error'
                ],
                'content' => [
                    'oneOf' => [
                        ['type' => 'string'],
                        [
                            'type' => 'array',
                            'items' => ['type' => 'string']
                        ]
                    ],
                    'description' => 'Response content, can be a string or an array of strings'
                ]
            ],
            'required' => ['isError', 'content']
        ];

        // Assemble the complete API specification.
        $apiSpec = [
            'openapi' => '3.1.0',
            'info'    => [
                'title'   => $this->title,
                'version' => $this->version
            ],
            'servers' => [
                [
                    'url' => $this->serverUrl
                ]
            ],
            'paths' => $this->paths,
            'components' => [
                'schemas' => $this->componentsSchemas
            ]
        ];

        return $apiSpec;
    }

    /**
     * Returns the API specification as a JSON string.
     *
     * @param AbstractOpenFunction $action The open function instance.
     *
     * @return string JSON representation of the API spec.
     */
    public function toJson(AbstractOpenFunction $action): string
    {
        $spec = $this->serialize($action);
        return json_encode($spec, JSON_PRETTY_PRINT);
    }
}