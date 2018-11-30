<?php

namespace TheCodingMachine\GraphQL\Controllers\Integration;

use function class_exists;
use Doctrine\Common\Annotations\AnnotationReader as DoctrineAnnotationReader;
use Exception;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\InputType;
use Mouf\Picotainer\Picotainer;
use PhpParser\Comment\Doc;
use PHPUnit\Framework\TestCase;
use PHPUnit\Util\Type;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Cache\Simple\NullCache;
use TheCodingMachine\GraphQL\Controllers\AnnotationReader;
use TheCodingMachine\GraphQL\Controllers\Fixtures\Integration\Models\Contact;
use TheCodingMachine\GraphQL\Controllers\GlobControllerQueryProvider;
use TheCodingMachine\GraphQL\Controllers\HydratorInterface;
use TheCodingMachine\GraphQL\Controllers\Mappers\GlobTypeMapper;
use TheCodingMachine\GraphQL\Controllers\Mappers\RecursiveTypeMapper;
use TheCodingMachine\GraphQL\Controllers\Mappers\RecursiveTypeMapperInterface;
use TheCodingMachine\GraphQL\Controllers\Mappers\TypeMapperInterface;
use TheCodingMachine\GraphQL\Controllers\QueryProviderInterface;
use TheCodingMachine\GraphQL\Controllers\Containers\BasicAutoWiringContainer;
use TheCodingMachine\GraphQL\Controllers\Containers\EmptyContainer;
use TheCodingMachine\GraphQL\Controllers\Schema;
use TheCodingMachine\GraphQL\Controllers\Security\AuthenticationServiceInterface;
use TheCodingMachine\GraphQL\Controllers\Security\AuthorizationServiceInterface;
use TheCodingMachine\GraphQL\Controllers\Security\VoidAuthenticationService;
use TheCodingMachine\GraphQL\Controllers\Security\VoidAuthorizationService;
use TheCodingMachine\GraphQL\Controllers\TypeGenerator;

class EndToEndTest /*extends TestCase*/
{
    /**
     * @var ContainerInterface
     */
    private $mainContainer;

    public function setUp()
    {
        $this->mainContainer = new Picotainer([
            Schema::class => function(ContainerInterface $container) {
                return new Schema($container->get(QueryProviderInterface::class), $container->get(BasicAutoWiringContainer::class));
            },
            QueryProviderInterface::class => function(ContainerInterface $container) {
                return new GlobControllerQueryProvider('TheCodingMachine\\GraphQL\\Controllers\\Fixtures\\Integration\\Controllers', $container->get(BasicAutoWiringContainer::class),
                    $container->get(BasicAutoWiringContainer::class), new NullCache());
            },
            BasicAutoWiringContainer::class => function(ContainerInterface $container) {
                return new BasicAutoWiringContainer(new EmptyContainer());
            },
            AuthorizationServiceInterface::class => function(ContainerInterface $container) {
                return new VoidAuthorizationService();
            },
            AuthenticationServiceInterface::class => function(ContainerInterface $container) {
                return new VoidAuthenticationService();
            },
            RecursiveTypeMapperInterface::class => function(ContainerInterface $container) {
                return new RecursiveTypeMapper($container->get(TypeMapperInterface::class));
            },
            TypeMapperInterface::class => function(ContainerInterface $container) {
                return new GlobTypeMapper('TheCodingMachine\\GraphQL\\Controllers\\Fixtures\\Integration\\Types',
                    $container->get(TypeGenerator::class),
                    $container->get(BasicAutoWiringContainer::class),
                    $container->get(AnnotationReader::class),
                    new NullCache()
                    );
            },
            TypeGenerator::class => function(ContainerInterface $container) {
                return new TypeGenerator();
            },
            AnnotationReader::class => function(ContainerInterface $container) {
                return new AnnotationReader(new DoctrineAnnotationReader());
            },
            HydratorInterface::class => function(ContainerInterface $container) {
                return new class implements HydratorInterface
                {
                    public function hydrate(array $data, InputType $type)
                    {
                        return new Contact($data['name']);
                    }
                };
            }
        ]);
    }

    public function testEndToEnd()
    {
        /**
         * @var Schema $schema
         */
        $schema = $this->mainContainer->get(Schema::class);
        $queryString = '
        query {
            getContacts {
                name
            }
        }
        ';

        $result = GraphQL::executeQuery(
            $schema,
            $queryString
        );

        var_dump($result);
    }

}
