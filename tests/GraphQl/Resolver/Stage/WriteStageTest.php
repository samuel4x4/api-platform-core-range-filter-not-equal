<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\Core\Tests\GraphQl\Resolver\Stage;

use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use ApiPlatform\Core\Tests\ProphecyTrait;
use ApiPlatform\GraphQl\Resolver\Stage\WriteStage;
use ApiPlatform\GraphQl\Serializer\SerializerContextBuilderInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

/**
 * @author Alan Poulain <contact@alanpoulain.eu>
 */
class WriteStageTest extends TestCase
{
    use ProphecyTrait;

    /** @var WriteStage */
    private $writeStage;
    private $resourceMetadataCollectionFactoryProphecy;
    private $dataPersisterProphecy;
    private $serializerContextBuilderProphecy;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->resourceMetadataCollectionFactoryProphecy = $this->prophesize(ResourceMetadataCollectionFactoryInterface::class);
        $this->dataPersisterProphecy = $this->prophesize(ContextAwareDataPersisterInterface::class);
        $this->serializerContextBuilderProphecy = $this->prophesize(SerializerContextBuilderInterface::class);

        $this->writeStage = new WriteStage(
            $this->resourceMetadataCollectionFactoryProphecy->reveal(),
            $this->dataPersisterProphecy->reveal(),
            $this->serializerContextBuilderProphecy->reveal()
        );
    }

    public function testNoData(): void
    {
        $resourceClass = 'myResource';
        $operationName = 'item_query';
        $resourceMetadata = (new ApiResource())->withGraphQlOperations([
            $operationName => (new Query()),
        ]);
        $this->resourceMetadataCollectionFactoryProphecy->create($resourceClass)->willReturn(new ResourceMetadataCollection($resourceClass, [$resourceMetadata]));

        $result = ($this->writeStage)(null, $resourceClass, $operationName, []);

        $this->assertNull($result);
    }

    public function testApplyDisabled(): void
    {
        $operationName = 'item_query';
        $resourceClass = 'myResource';
        $resourceMetadata = new ResourceMetadataCollection($resourceClass, [(new ApiResource())->withGraphQlOperations([
            $operationName => (new Query())->withWrite(false),
        ])]);
        $this->resourceMetadataCollectionFactoryProphecy->create($resourceClass)->willReturn($resourceMetadata);

        $data = new \stdClass();
        $result = ($this->writeStage)($data, $resourceClass, $operationName, []);

        $this->assertSame($data, $result);
    }

    public function testApplyDelete(): void
    {
        $operationName = 'delete';
        $resourceClass = 'myResource';
        $context = [];
        $resourceMetadata = new ResourceMetadataCollection($resourceClass, [(new ApiResource())->withGraphQlOperations([
            $operationName => new Mutation(),
        ])]);
        $this->resourceMetadataCollectionFactoryProphecy->create($resourceClass)->willReturn($resourceMetadata);

        $denormalizationContext = ['denormalization' => true];
        $this->serializerContextBuilderProphecy->create($resourceClass, $operationName, $context, false)->willReturn($denormalizationContext);

        $data = new \stdClass();
        $this->dataPersisterProphecy->remove($data, $denormalizationContext)->shouldBeCalled();
        $this->dataPersisterProphecy->persist(Argument::cetera())->shouldNotBeCalled();

        $result = ($this->writeStage)($data, $resourceClass, $operationName, $context);

        $this->assertNull($result);
    }

    public function testApply(): void
    {
        $operationName = 'create';
        $resourceClass = 'myResource';
        $context = [];
        $resourceMetadata = new ResourceMetadataCollection($resourceClass, [(new ApiResource())->withGraphQlOperations([
            $operationName => new Mutation(),
        ])]);
        $this->resourceMetadataCollectionFactoryProphecy->create($resourceClass)->willReturn($resourceMetadata);

        $denormalizationContext = ['denormalization' => true];
        $this->serializerContextBuilderProphecy->create($resourceClass, $operationName, $context, false)->willReturn($denormalizationContext);

        $data = new \stdClass();
        $persistedData = new \stdClass();
        $this->dataPersisterProphecy->remove(Argument::cetera())->shouldNotBeCalled();
        $this->dataPersisterProphecy->persist($data, $denormalizationContext)->shouldBeCalled()->willReturn($persistedData);

        $result = ($this->writeStage)($data, $resourceClass, $operationName, $context);

        $this->assertSame($persistedData, $result);
    }

    /**
     * @group legacy
     * @expectedDeprecation Not returning an object from ApiPlatform\Core\DataPersister\DataPersisterInterface::persist() is deprecated since API Platform 2.3 and will not be supported in API Platform 3.
     */
    public function testLegacyApply(): void
    {
        $operationName = 'create';
        $resourceClass = 'myResource';
        $context = [];
        $resourceMetadata = new ResourceMetadataCollection($resourceClass, [(new ApiResource())->withGraphQlOperations([
            $operationName => new Mutation(),
        ])]);
        $this->resourceMetadataCollectionFactoryProphecy->create($resourceClass)->willReturn($resourceMetadata);

        $denormalizationContext = ['denormalization' => true];
        $this->serializerContextBuilderProphecy->create($resourceClass, $operationName, $context, false)->willReturn($denormalizationContext);

        $data = new \stdClass();
        $this->dataPersisterProphecy->remove(Argument::cetera())->shouldNotBeCalled();
        $this->dataPersisterProphecy->persist($data, $denormalizationContext)->shouldBeCalled();

        $result = ($this->writeStage)($data, $resourceClass, $operationName, $context);

        $this->assertNull($result);
    }
}
