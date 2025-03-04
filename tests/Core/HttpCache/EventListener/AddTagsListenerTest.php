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

namespace ApiPlatform\Core\Tests\HttpCache\EventListener;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Core\HttpCache\EventListener\AddTagsListener;
use ApiPlatform\Core\Tests\ProphecyTrait;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Tests\Fixtures\TestBundle\Entity\Dummy;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @group legacy
 */
class AddTagsListenerTest extends TestCase
{
    use ProphecyTrait;

    public function testDoNotSetHeaderWhenMethodNotCacheable()
    {
        $iriConverterProphecy = $this->prophesize(IriConverterInterface::class);

        $request = new Request([], [], ['_resources' => ['/foo', '/bar'], '_api_resource_class' => Dummy::class, '_api_item_operation_name' => 'get']);
        $request->setMethod('PUT');

        $response = new Response();
        $response->setPublic();
        $response->setEtag('foo');

        $event = new ResponseEvent(
            $this->prophesize(HttpKernelInterface::class)->reveal(),
            $request,
            HttpKernelInterface::MASTER_REQUEST,
            $response
        );

        $listener = new AddTagsListener($iriConverterProphecy->reveal());
        $listener->onKernelResponse($event);

        $this->assertFalse($response->headers->has('Cache-Tags'));
    }

    public function testDoNotSetHeaderWhenResponseNotCacheable()
    {
        $iriConverterProphecy = $this->prophesize(IriConverterInterface::class);

        $request = new Request([], [], ['_resources' => ['/foo', '/bar'], '_api_resource_class' => Dummy::class, '_api_item_operation_name' => 'get']);
        $response = new Response();
        $event = new ResponseEvent(
            $this->prophesize(HttpKernelInterface::class)->reveal(),
            $request,
            HttpKernelInterface::MASTER_REQUEST,
            $response
        );

        $listener = new AddTagsListener($iriConverterProphecy->reveal());
        $listener->onKernelResponse($event);

        $this->assertFalse($response->headers->has('Cache-Tags'));
    }

    public function testDoNotSetHeaderWhenNotAnApiOperation()
    {
        $iriConverterProphecy = $this->prophesize(IriConverterInterface::class);

        $response = new Response();
        $response->setPublic();
        $response->setEtag('foo');

        $event = new ResponseEvent(
            $this->prophesize(HttpKernelInterface::class)->reveal(),
            new Request([], [], ['_resources' => ['/foo', '/bar']]),
            HttpKernelInterface::MASTER_REQUEST,
            $response
        );

        $listener = new AddTagsListener($iriConverterProphecy->reveal());
        $listener->onKernelResponse($event);

        $this->assertFalse($response->headers->has('Cache-Tags'));
    }

    public function testDoNotSetHeaderWhenEmptyTagList()
    {
        $iriConverterProphecy = $this->prophesize(IriConverterInterface::class);

        $response = new Response();
        $response->setPublic();
        $response->setEtag('foo');

        $event = new ResponseEvent(
            $this->prophesize(HttpKernelInterface::class)->reveal(),
            new Request([], [], ['_resources' => [], '_api_resource_class' => Dummy::class, '_api_item_operation_name' => 'get']),
            HttpKernelInterface::MASTER_REQUEST,
            $response
        );

        $listener = new AddTagsListener($iriConverterProphecy->reveal());
        $listener->onKernelResponse($event);

        $this->assertFalse($response->headers->has('Cache-Tags'));
    }

    public function testAddTags()
    {
        $iriConverterProphecy = $this->prophesize(IriConverterInterface::class);

        $response = new Response();
        $response->setPublic();
        $response->setEtag('foo');

        $event = new ResponseEvent(
            $this->prophesize(HttpKernelInterface::class)->reveal(),
            new Request([], [], ['_resources' => ['/foo', '/bar'], '_api_resource_class' => Dummy::class, '_api_item_operation_name' => 'get']),
            HttpKernelInterface::MASTER_REQUEST,
            $response
        );

        $listener = new AddTagsListener($iriConverterProphecy->reveal());
        $listener->onKernelResponse($event);

        $this->assertSame('/foo,/bar', $response->headers->get('Cache-Tags'));
    }

    public function testAddCollectionIri()
    {
        $iriConverterProphecy = $this->prophesize(IriConverterInterface::class);
        $iriConverterProphecy->getIriFromResourceClass(Dummy::class, null, 1, Argument::type('array'))->willReturn('/dummies')->shouldBeCalled();

        $response = new Response();
        $response->setPublic();
        $response->setEtag('foo');

        $event = new ResponseEvent(
            $this->prophesize(HttpKernelInterface::class)->reveal(),
            new Request([], [], ['_resources' => ['/foo', '/bar'], '_api_resource_class' => Dummy::class, '_api_collection_operation_name' => 'get']),
            HttpKernelInterface::MASTER_REQUEST,
            $response
        );

        $listener = new AddTagsListener($iriConverterProphecy->reveal());
        $listener->onKernelResponse($event);

        $this->assertSame('/foo,/bar,/dummies', $response->headers->get('Cache-Tags'));
    }

    public function testAddCollectionIriWhenCollectionIsEmpty()
    {
        $iriConverterProphecy = $this->prophesize(IriConverterInterface::class);
        $iriConverterProphecy->getIriFromResourceClass(Dummy::class, null, 1, Argument::type('array'))->willReturn('/dummies')->shouldBeCalled();

        $response = new Response();
        $response->setPublic();
        $response->setEtag('foo');

        $event = new ResponseEvent(
            $this->prophesize(HttpKernelInterface::class)->reveal(),
            new Request([], [], ['_resources' => [], '_api_resource_class' => Dummy::class, '_api_collection_operation_name' => 'get']),
            HttpKernelInterface::MASTER_REQUEST,
            $response
        );

        $listener = new AddTagsListener($iriConverterProphecy->reveal());
        $listener->onKernelResponse($event);

        $this->assertSame('/dummies', $response->headers->get('Cache-Tags'));
    }

    public function testAddSubResourceCollectionIri()
    {
        $iriConverterProphecy = $this->prophesize(IriConverterInterface::class);
        $iriConverterProphecy->getIriFromResourceClass(Dummy::class, null, 1, Argument::type('array'))->willReturn('/dummies')->shouldBeCalled();

        $response = new Response();
        $response->setPublic();
        $response->setEtag('foo');

        $event = new ResponseEvent(
            $this->prophesize(HttpKernelInterface::class)->reveal(),
            new Request([], [], ['_resources' => ['/foo', '/bar'], '_api_resource_class' => Dummy::class, '_api_subresource_operation_name' => 'api_dummies_relatedDummies_get_subresource', '_api_subresource_context' => ['collection' => true]]),
            HttpKernelInterface::MASTER_REQUEST,
            $response
        );

        $listener = new AddTagsListener($iriConverterProphecy->reveal());
        $listener->onKernelResponse($event);

        $this->assertSame('/foo,/bar,/dummies', $response->headers->get('Cache-Tags'));
    }

    public function testAddTagsWithXKey()
    {
        $iriConverterProphecy = $this->prophesize(IriConverterInterface::class);
        $iriConverterProphecy->getIriFromResourceClass(Dummy::class, 'get', 1, Argument::type('array'))->willReturn('/dummies')->shouldBeCalled();

        $resourceMetadataCollectionFactoryProphecy = $this->prophesize(ResourceMetadataCollectionFactoryInterface::class);
        $dummyMetadata = new ResourceMetadataCollection(Dummy::class, [(new ApiResource())->withOperations(new Operations(['get' => new GetCollection()]))]);
        $resourceMetadataCollectionFactoryProphecy->create(Dummy::class)->willReturn($dummyMetadata);

        $response = new Response();
        $response->setPublic();
        $response->setEtag('foo');

        $event = new ResponseEvent(
            $this->prophesize(HttpKernelInterface::class)->reveal(),
            new Request([], [], ['_resources' => ['/foo', '/bar'], '_api_resource_class' => Dummy::class, '_api_item_operation_name' => 'get']),
            HttpKernelInterface::MASTER_REQUEST,
            $response
        );

        $listener = new AddTagsListener($iriConverterProphecy->reveal(), $resourceMetadataCollectionFactoryProphecy->reveal(), true);
        $listener->onKernelResponse($event);

        $this->assertSame('/foo,/bar,/dummies', $response->headers->get('Cache-Tags'));
        $this->assertSame('/foo /bar /dummies', $response->headers->get('xkey'));
    }
}
