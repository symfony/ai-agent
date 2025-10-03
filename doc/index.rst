Symfony AI - Agent Component
============================

The Agent component provides a framework for building AI agents that, sits on top of the Platform and Store components,
allowing you to create agents that can interact with users, perform tasks, and manage workflows.

Installation
------------

Install the component using Composer:

.. code-block:: terminal

    $ composer require symfony/ai-agent

Basic Usage
-----------

To instantiate an agent, you need to pass a ``Symfony\AI\Platform\PlatformInterface`` and a
``Symfony\AI\Platform\Model`` instance to the ``Symfony\AI\Agent\Agent`` class::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
    use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;

    $platform = PlatformFactory::create($apiKey);
    $model = new Gpt(Gpt::GPT_4O_MINI);

    $agent = new Agent($platform, $model);

You can then run the agent with a ``Symfony\AI\Platform\Message\MessageBagInterface`` instance as input and an optional
array of options::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    // Platform & LLM instantiation

    $agent = new Agent($platform, $model);
    $input = new MessageBag(
        Message::forSystem('You are a helpful chatbot answering questions about LLM agent.'),
        Message::ofUser('Hello, how are you?'),
    );
    $result = $agent->call($messages);

    echo $result->getContent(); // "I'm fine, thank you. How can I help you today?"


The structure of the input message bag is flexible, see `Platform Component`_ for more details on how to use it.

Options
~~~~~~~

As with the Platform component, you can pass options to the agent when running it. These options configure the agent's
behavior, for example available tools to execute, or are forwarded to the underlying platform and model.

Tools
-----

To integrate LLMs with your application, Symfony AI supports tool calling out of the box. Tools are services that can be
called by the LLM to provide additional features or process data.

Tool calling can be enabled by registering the processors in the agent::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Agent\Toolbox\AgentProcessor;
    use Symfony\AI\Agent\Toolbox\Toolbox;

    // Platform & LLM instantiation

    $yourTool = new YourTool();

    $toolbox = new Toolbox([$yourTool]);
    $toolProcessor = new AgentProcessor($toolbox);

    $agent = new Agent($platform, $model, inputProcessors: [$toolProcessor], outputProcessors: [$toolProcessor]);

Custom tools can basically be any class, but must configure by the ``#[AsTool]`` attribute::

    use Symfony\AI\Toolbox\Attribute\AsTool;

    #[AsTool('company_name', 'Provides the name of your company')]
    final class CompanyName
    {
        public function __invoke(): string
        {
            return 'ACME Corp.';
        }
    }

Tool Return Value
~~~~~~~~~~~~~~~~~

In the end, the tool's result needs to be a string, but Symfony AI converts arrays and objects, that implement the
JsonSerializable interface, to JSON strings for you. So you can return arrays or objects directly from your tool.

Tool Methods
~~~~~~~~~~~~

You can configure the method to be called by the LLM with the #[AsTool] attribute and have multiple tools per class::

    use Symfony\AI\Toolbox\Attribute\AsTool;

    #[AsTool(
        name: 'weather_current',
        description: 'get current weather for a location',
        method: 'current',
    )]
    #[AsTool(
        name: 'weather_forecast',
        description: 'get weather forecast for a location',
        method: 'forecast',
    )]
    final readonly class OpenMeteo
    {
        public function current(float $latitude, float $longitude): array
        {
            // ...
        }

        public function forecast(float $latitude, float $longitude): array
        {
            // ...
        }
    }

Tool Parameters
~~~~~~~~~~~~~~~

Symfony AI generates a JSON Schema representation for all tools in the Toolbox based on the #[AsTool] attribute and
method arguments and param comments in the doc block. Additionally, JSON Schema support validation rules, which are
partially support by LLMs like GPT.

Parameter Validation with #[With] Attribute
...........................................

To leverage JSON Schema validation rules, configure the ``#[With]`` attribute on the method arguments of your tool::

    use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
    use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;

    #[AsTool('my_tool', 'Example tool with parameters requirements.')]
    final class MyTool
    {
        /**
         * @param string $name   The name of an object
         * @param int    $number The number of an object
         * @param array<string> $categories List of valid categories
         */
        public function __invoke(
            #[With(pattern: '/([a-z0-1]){5}/')]
            string $name,
            #[With(minimum: 0, maximum: 10)]
            int $number,
            #[With(enum: ['tech', 'business', 'science'])]
            array $categories,
        ): string {
            // ...
        }
    }

See attribute class ``Symfony\AI\Platform\Contract\JsonSchema\Attribute\With`` for all available options.

Automatic Enum Validation
.........................

For PHP backed enums, Symfony AI provides automatic validation without requiring any ``#[With]`` attributes::

    enum Priority: int
    {
        case LOW = 1;
        case NORMAL = 5;
        case HIGH = 10;
    }

    enum ContentType: string
    {
        case ARTICLE = 'article';
        case TUTORIAL = 'tutorial';
        case NEWS = 'news';
    }

    #[AsTool('content_search', 'Search for content with automatic enum validation.')]
    final class ContentSearchTool
    {
        /**
         * @param array<string> $keywords The search keywords
         * @param ContentType   $type     The content type to search for
         * @param Priority      $priority Minimum priority level
         * @param ContentType|null $fallback Optional fallback content type
         */
        public function __invoke(
            array $keywords,
            ContentType $type,
            Priority $priority,
            ?ContentType $fallback = null,
        ): array {
            // Enums are automatically validated - no #[With] attribute needed!
            // ...
        }
    }

This eliminates the need for manual ``#[With(enum: [...])]`` attributes when using PHP's native backed enum types.

.. note::

    Please be aware, that this is only converted in a JSON Schema for the LLM to respect, but not validated by Symfony AI.

Third-Party Tools
~~~~~~~~~~~~~~~~~

In some cases you might want to use third-party tools, which are not part of your application. Adding the ``#[AsTool]``
attribute to the class is not possible in those cases, but you can explicitly register the tool in the MemoryFactory::

    use Symfony\AI\Agent\Toolbox\Toolbox;
    use Symfony\AI\Agent\Toolbox\ToolFactory\MemoryToolFactory;
    use Symfony\Component\Clock\Clock;

    $metadataFactory = (new MemoryToolFactory())
        ->addTool(Clock::class, 'clock', 'Get the current date and time', 'now');
    $toolbox = new Toolbox($metadataFactory, [new Clock()]);

.. note::

    Please be aware that not all return types are supported by the toolbox, so a decorator might still be needed.

This can be combined with the ChainFactory which enables you to use explicitly registered tools and ``#[AsTool]`` tagged
tools in the same chain - which even enables you to overwrite the pre-existing configuration of a tool::

    use Symfony\AI\Agent\Toolbox\Toolbox;
    use Symfony\AI\Agent\Toolbox\ToolFactory\ChainFactory;
    use Symfony\AI\Agent\Toolbox\ToolFactory\MemoryToolFactory;
    use Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory;

    $reflectionFactory = new ReflectionToolFactory(); // Register tools with #[AsTool] attribute
    $metadataFactory = (new MemoryToolFactory())      // Register or overwrite tools explicitly
        ->addTool(...);
    $toolbox = new Toolbox(new AgentFactory($metadataFactory, $reflectionFactory), [...]);

.. note::

    The order of the factories in the ChainFactory matters, as the first factory has the highest priority.

Agent uses Agent 🤯
~~~~~~~~~~~~~~~~~~

Similar to third-party tools, an agent can also use an different agent as a tool. This can be useful to encapsulate
complex logic or to reuse an agent in multiple places or hide sub-agents from the LLM::

    use Symfony\AI\Agent\Toolbox\Tool\Agent;
    use Symfony\AI\Agent\Toolbox\Toolbox;
    use Symfony\AI\Agent\Toolbox\ToolFactory\MemoryToolFactory;

    // agent was initialized before

    $agentTool = new Agent($agent);
    $metadataFactory = (new MemoryToolFactory())
        ->addTool($agentTool, 'research_agent', 'Meaningful description for sub-agent');
    $toolbox = new Toolbox($metadataFactory, [$agentTool]);

Fault Tolerance
~~~~~~~~~~~~~~~

To gracefully handle errors that occur during tool calling, e.g. wrong tool names or runtime errors, you can use the
``FaultTolerantToolbox`` as a decorator for the Toolbox. It will catch the exceptions and return readable error messages
to the LLM::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Agent\Toolbox\AgentProcessor;
    use Symfony\AI\Agent\Toolbox\FaultTolerantToolbox;

    // Platform, LLM & Toolbox instantiation

    $toolbox = new FaultTolerantToolbox($innerToolbox);
    $toolProcessor = new AgentProcessor($toolbox);

    $agent = new Agent($platform, $model, inputProcessor: [$toolProcessor], outputProcessor: [$toolProcessor]);

If you want to expose the underlying error to the LLM, you can throw a custom exception that implements `ToolExecutionExceptionInterface`::

    use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionExceptionInterface;

    class EntityNotFoundException extends \RuntimeException implements ToolExecutionExceptionInterface
    {
        public function __construct(private string $entityName, private int $id)
        {
        }

        public function getToolCallResult(): mixed
        {
            return \sprintf('No %s found with id %d', $this->entityName, $this->id);
        }
    }

    #[AsTool('get_user_age', 'Get age by user id')]
    class GetUserAge
    {
        public function __construct(private UserRepository $userRepository)
        {
        }

        public function __invoke(int $id): int
        {
            $user = $this->userRepository->find($id) ?? throw new EntityNotFoundException('user', $id);

            return $user->getAge();
        }
    }

Tool Filtering
~~~~~~~~~~~~~~

To limit the tools provided to the LLM in a specific agent call to a subset of the configured tools, you can use the
tools option with a list of tool names::

    $this->agent->call($messages, ['tools' => ['tavily_search']]);

Tool Result Interception
~~~~~~~~~~~~~~~~~~~~~~~~

To react to the result of a tool, you can implement an EventListener or EventSubscriber, that listens to the
``ToolCallsExecuted`` event. This event is dispatched after the Toolbox executed all current tool calls and enables you
to skip the next LLM call by setting a result yourself::

    $eventDispatcher->addListener(ToolCallsExecuted::class, function (ToolCallsExecuted $event): void {
        foreach ($event->toolCallResults as $toolCallResult) {
            if (str_starts_with($toolCallResult->toolCall->name, 'weather_')) {
                $event->result = new ObjectResult($toolCallResult->result);
            }
        }
    });

Tool Call Lifecycle Events
~~~~~~~~~~~~~~~~~~~~~~~~~~

If you need to react more granularly to the lifecycle of individual tool calls, you can listen to the
``ToolCallArgumentsResolved``, ``ToolCallSucceeded`` and ``ToolCallFailed`` events. These are dispatched at different stages::

    $eventDispatcher->addListener(ToolCallArgumentsResolved::class, function (ToolCallArgumentsResolved $event): void {
        // Let the client know, that the tool $event->toolCall->name was executed
    });

    $eventDispatcher->addListener(ToolCallSucceeded::class, function (ToolCallSucceeded $event): void {
        // Let the client know, that the tool $event->toolCall->name successfully returned the result $event->result
    });

    $eventDispatcher->addListener(ToolCallFailed::class, function (ToolCallFailed $event): void {
        // Let the client know, that the tool $event->toolCall->name failed with the exception: $event->exception
    });

Keeping Tool Messages
~~~~~~~~~~~~~~~~~~~~~

Sometimes you might wish to keep the tool messages (AssistantMessage containing the toolCalls and ToolCallMessage
containing the result) in the context. Enable the keepToolMessages flag of the toolbox' AgentProcessor to ensure those
messages will be added to your MessageBag::

    use Symfony\AI\Agent\Toolbox\AgentProcessor;
    use Symfony\AI\Agent\Toolbox\Toolbox;

    // Platform & LLM instantiation
    $messages = new MessageBag(
        Message::forSystem(<<<PROMPT
            Please answer all user questions only using the similary_search tool. Do not add information and if you cannot
            find an answer, say so.
            PROMPT),
        Message::ofUser('...') // The user's question.
    );

    $yourTool = new YourTool();

    $toolbox = new Toolbox([$yourTool]);
    $toolProcessor = new AgentProcessor($toolbox, keepToolMessages: true);

    $agent = new Agent($platform, $model, inputProcessor: [$toolProcessor], outputProcessor: [$toolProcessor]);
    $result = $agent->call($messages);
    // $messages will now include the tool messages

Code Examples (with built-in tools)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

* `Brave Tool`_
* `Clock Tool`_
* `Crawler Tool`_
* `Mapbox Geocode Tool`_
* `Mapbox Reverse Geocode Tool`_
* `SerpAPI Tool`_
* `Tavily Tool`_
* `Weather Tool with Event Listener`_
* `Wikipedia Tool`_
* `YouTube Transcriber Tool`_

Retrieval Augmented Generation (RAG)
------------------------------------

In combination with the `Store Component`_, the Agent component can be used to build agents that perform Retrieval
Augmented Generation (RAG). This allows the agent to retrieve relevant documents from a store and use them to generate
more accurate and context-aware results. Therefore, the component provides a built-in tool called
``Symfony\AI\Agent\Toolbox\Tool\SimilaritySearch``::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Agent\Toolbox\AgentProcessor;
    use Symfony\AI\Agent\Toolbox\Tool\SimilaritySearch;
    use Symfony\AI\Agent\Toolbox\Toolbox;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    // Initialize Platform & Models

    $similaritySearch = new SimilaritySearch($model, $store);
    $toolbox = new Toolbox([$similaritySearch]);
    $processor = new Agent($toolbox);
    $agent = new Agent($platform, $model, [$processor], [$processor]);

    $messages = new MessageBag(
        Message::forSystem(<<<PROMPT
            Please answer all user questions only using the similary_search tool. Do not add information and if you cannot
            find an answer, say so.
            PROMPT),
        Message::ofUser('...') // The user's question.
    );
    $result = $agent->call($messages);

Code Examples
~~~~~~~~~~~~~

* `RAG with MongoDB`_
* `RAG with Pinecone`_

Structured Output
-----------------

A typical use-case of LLMs is to classify and extract data from unstructured sources, which is supported by some models
by features like Structured Output or providing a Response Format.

PHP Classes as Output
~~~~~~~~~~~~~~~~~~~~~

Symfony AI supports that use-case by abstracting the hustle of defining and providing schemas to the LLM and converting
the result back to PHP objects.

To achieve this, a specific agent processor needs to be registered::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Agent\StructuredOutput\AgentProcessor;
    use Symfony\AI\Agent\StructuredOutput\ResponseFormatFactory;
    use Symfony\AI\Fixtures\StructuredOutput\MathReasoning;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;
    use Symfony\Component\Serializer\Encoder\JsonEncoder;
    use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
    use Symfony\Component\Serializer\Serializer;

    // Initialize Platform and LLM

    $serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
    $processor = new AgentProcessor(new ResponseFormatFactory(), $serializer);
    $agent = new Agent($platform, $model, [$processor], [$processor]);

    $messages = new MessageBag(
        Message::forSystem('You are a helpful math tutor. Guide the user through the solution step by step.'),
        Message::ofUser('how can I solve 8x + 7 = -23'),
    );
    $result = $agent->call($messages, ['output_structure' => MathReasoning::class]);

    dump($result->getContent()); // returns an instance of `MathReasoning` class

Array Structures as Output
~~~~~~~~~~~~~~~~~~~~~~~~~~

Also PHP array structures as response_format are supported, which also requires the agent processor mentioned above::

    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    // Initialize Platform, LLM and agent with processors and Clock tool

    $messages = new MessageBag(Message::ofUser('What date and time is it?'));
    $result = $agent->call($messages, ['response_format' => [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'clock',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'date' => ['type' => 'string', 'description' => 'The current date in the format YYYY-MM-DD.'],
                    'time' => ['type' => 'string', 'description' => 'The current time in the format HH:MM:SS.'],
                ],
                'required' => ['date', 'time'],
                'additionalProperties' => false,
            ],
        ],
    ]]);

    dump($result->getContent()); // returns an array

Code Examples
~~~~~~~~~~~~~

* `Structured Output with PHP class`_
* `Structured Output with array`_

Input & Output Processing
-------------------------

The behavior of the agent is extendable with services that implement InputProcessor and/or OutputProcessor interface.
They are provided while instantiating the agent instance::

    use Symfony\AI\Agent\Agent;

    // Initialize Platform, LLM and processors

    $agent = new Agent($platform, $model, $inputProcessors, $outputProcessors);

InputProcessor
~~~~~~~~~~~~~~

InputProcessor instances are called in the agent before handing over the MessageBag and the $options array to the LLM
and are able to mutate both on top of the Input instance provided::

    use Symfony\AI\Agent\Input;
    use Symfony\AI\Agent\InputProcessorInterface;
    use Symfony\AI\Platform\Message\AssistantMessage;

    final class MyProcessor implements InputProcessorInterface
    {
        public function processInput(Input $input): void
        {
            // mutate options
            $options = $input->getOptions();
            $options['foo'] = 'bar';
            $input->setOptions($options);

            // mutate MessageBag
            $input->messages->append(new AssistantMessage(sprintf('Please answer using the locale %s', $this->locale)));
        }
    }

OutputProcessor
~~~~~~~~~~~~~~~

OutputProcessor instances are called after the model provided a result and can - on top of options and messages - mutate
or replace the given result::

    use Symfony\AI\Agent\Output;
    use Symfony\AI\Agent\OutputProcessorInterface;

    final class MyProcessor implements OutputProcessorInterface
    {
        public function processOutput(Output $output): void
        {
            // mutate result
            if (str_contains($output->result->getContent(), self::STOP_WORD)) {
                $output->result = new TextResult('Sorry, we were unable to find relevant information.')
            }
        }
    }

Agent Awareness
~~~~~~~~~~~~~~~

Both, Input and Output instances, provide access to the LLM used by the agent, but the agent itself is only provided,
in case the processor implemented the AgentAwareInterface interface, which can be combined with using the
AgentAwareTrait::

    use Symfony\AI\Agent\AgentAwareInterface;
    use Symfony\AI\Agent\AgentAwareTrait;
    use Symfony\AI\Agent\Output;
    use Symfony\AI\Agent\OutputProcessorInterface;

    final class MyProcessor implements OutputProcessorInterface, AgentAwareInterface
    {
        use AgentAwareTrait;

        public function processOutput(Output $out): void
        {
            // additional agent interaction
            $result = $this->agent->call(...);
        }
    }

Agent Memory Management
-----------------------

Symfony AI supports adding contextual memory to agent conversations, allowing the model to recall past interactions or
relevant information from different sources. Memory providers inject information into the system prompt, providing the
model with context without changing your application logic.

Using Memory
^^^^^^^^^^^^

Memory integration is handled through the ``MemoryInputProcessor`` and one or more ``MemoryProviderInterface`` implementations::

    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Agent\Memory\MemoryInputProcessor;
    use Symfony\AI\Agent\Memory\StaticMemoryProvider;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    // Platform & LLM instantiation

    $personalFacts = new StaticMemoryProvider(
        'My name is Wilhelm Tell',
        'I wish to be a swiss national hero',
        'I am struggling with hitting apples but want to be professional with the bow and arrow',
    );
    $memoryProcessor = new MemoryInputProcessor($personalFacts);

    $agent = new Agent($platform, $model, [$memoryProcessor]);
    $messages = new MessageBag(Message::ofUser('What do we do today?'));
    $result = $agent->call($messages);

Memory Providers
^^^^^^^^^^^^^^^^

The library includes several memory provider implementations that are ready to use out of the box.

Static Memory
.............

Static memory provides fixed information to the agent, such as user preferences, application context, or any other
information that should be consistently available without being directly added to the system prompt::

    use Symfony\AI\Agent\Memory\StaticMemoryProvider;

    $staticMemory = new StaticMemoryProvider(
        'The user is allergic to nuts',
        'The user prefers brief explanations',
    );

Embedding Provider
..................

This provider leverages vector storage to inject relevant knowledge based on the user's current message. It can be used
for retrieving general knowledge from a store or recalling past conversation pieces that might be relevant::

    use Symfony\AI\Agent\Memory\EmbeddingProvider;

    $embeddingsMemory = new EmbeddingProvider(
        $platform,
        $embeddings, // Your embeddings model for vectorizing user messages
        $store       // Your vector store to query for relevant context
    );

Dynamic Memory Control
^^^^^^^^^^^^^^^^^^^^^^

Memory is globally configured for the agent, but you can selectively disable it for specific calls when needed. This is
useful when certain interactions shouldn't be influenced by the memory context::

    $result = $agent->call($messages, [
        'use_memory' => false, // Disable memory for this specific call
    ]);


Testing
-------

MockAgent
^^^^^^^^^

For testing purposes, the Agent component provides a ``MockAgent`` class that behaves like Symfony's ``MockHttpClient``.
It provides predictable responses without making external API calls and includes assertion methods for verifying interactions::

    use Symfony\AI\Agent\MockAgent;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $agent = new MockAgent([
        'What is Symfony?' => 'Symfony is a PHP web framework',
        'Tell me about caching' => 'Symfony provides powerful caching',
    ]);

    $messages = new MessageBag(Message::ofUser('What is Symfony?'));
    $result = $agent->call($messages);

    echo $result->getContent(); // "Symfony is a PHP web framework"

Call Tracking and Assertions::

    // Verify agent interactions
    $agent->assertCallCount(1);
    $agent->assertCalledWith('What is Symfony?');

    // Get detailed call information
    $calls = $agent->getCalls();
    $lastCall = $agent->getLastCall();

    // Reset call tracking
    $agent->reset();

MockResponse Objects
^^^^^^^^^^^^^^^^^^^^

Similar to ``MockHttpClient``, you can use ``MockResponse`` objects for more complex scenarios::

    use Symfony\AI\Agent\MockResponse;

    $complexResponse = new MockResponse('Detailed response content');
    $agent = new MockAgent([
        'complex query' => $complexResponse,
        'simple query' => 'Simple string response',
    ]);

Callable Responses
^^^^^^^^^^^^^^^^^^

Like ``MockHttpClient``, ``MockAgent`` supports callable responses for dynamic behavior::

    $agent = new MockAgent();

    // Dynamic response based on input and context
    $agent->addResponse('weather', function ($messages, $options, $input) {
        $messageCount = count($messages->getMessages());
        return "Weather info (context: {$messageCount} messages)";
    });

    // Callable can return string or MockResponse
    $agent->addResponse('complex', function ($messages, $options, $input) {
        return new MockResponse("Complex response for: {$input}");
    });


Service Testing Example
^^^^^^^^^^^^^^^^^^^^^^^

Testing a service that uses an agent::

    class ChatServiceTest extends TestCase
    {
        public function testChatResponse(): void
        {
            $agent = new MockAgent([
                'Hello' => 'Hi there! How can I help?',
            ]);

            $chatService = new ChatService($agent);
            $response = $chatService->processMessage('Hello');

            $this->assertSame('Hi there! How can I help?', $response);
            $agent->assertCallCount(1);
            $agent->assertCalledWith('Hello');
        }
    }

The ``MockAgent`` provides all the benefits of traditional mocks while offering a more intuitive API for AI agent testing,
making your tests more reliable and easier to maintain.

Code Examples
~~~~~~~~~~~~~

* `Chat with static memory`_
* `Chat with embedding search memory`_


.. _`Platform Component`: https://github.com/symfony/ai-platform
.. _`Brave Tool`: https://github.com/symfony/ai/blob/main/examples/toolbox/brave.php
.. _`Clock Tool`: https://github.com/symfony/ai/blob/main/examples/toolbox/clock.php
.. _`Crawler Tool`: https://github.com/symfony/ai/blob/main/examples/toolbox/brave.php
.. _`Mapbox Geocode Tool`: https://github.com/symfony/ai/blob/main/examples/toolbox/mapbox-geocode.php
.. _`Mapbox Reverse Geocode Tool`: https://github.com/symfony/ai/blob/main/examples/toolbox/mapbox-reverse-geocode.php
.. _`SerpAPI Tool`: https://github.com/symfony/ai/blob/main/examples/toolbox/serpapi.php
.. _`Tavily Tool`: https://github.com/symfony/ai/blob/main/examples/toolbox/tavily.php
.. _`Weather Tool with Event Listener`: https://github.com/symfony/ai/blob/main/examples/toolbox/weather-event.php
.. _`Wikipedia Tool`: https://github.com/symfony/ai/blob/main/examples/openai/toolcall-stream.php
.. _`YouTube Transcriber Tool`: https://github.com/symfony/ai/blob/main/examples/openai/toolcall.php
.. _`Store Component`: https://github.com/symfony/ai-store
.. _`RAG with MongoDB`: https://github.com/symfony/ai/blob/main/examples/rag/mongodb.php
.. _`RAG with Pinecone`: https://github.com/symfony/ai/blob/main/examples/rag/pinecone.php
.. _`Structured Output with PHP class`: https://github.com/symfony/ai/blob/main/examples/openai/structured-output-math.php
.. _`Structured Output with array`: https://github.com/symfony/ai/blob/main/examples/openai/structured-output-clock.php
.. _`Chat with static memory`: https://github.com/symfony/ai/blob/main/examples/memory/static.php
.. _`Chat with embedding search memory`: https://github.com/symfony/ai/blob/main/examples/memory/mariadb.php
