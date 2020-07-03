<?php

namespace LdapRecord\Tests\Models;

use Mockery as m;
use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Tests\TestCase;
use LdapRecord\Query\Collection;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Models\Relations\HasOne;

class ModelHasOneTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        Container::getInstance()->add(new Connection());
    }

    public function test_get()
    {
        $relation = $this->getRelation();

        $parent = $relation->getParent();
        $parent->shouldReceive('getFirstAttribute')->once()->with('manager')->andReturn('foo');
        $parent->shouldReceive('newCollection')->once()->andReturn(new Collection([$related = new Entry()]));

        $query = $relation->getQuery();
        $query->shouldReceive('select')->once()->with(['*'])->andReturnSelf();
        $query->shouldReceive('find')->once()->with('foo')->andReturn(new Entry());

        $this->assertEquals($related, $relation->get()->first());
    }

    public function test_attach()
    {
        $relation = $this->getRelation();

        $related = new Entry();
        $related->setDn('foo');

        $parent = $relation->getParent();
        $parent->shouldReceive('setAttribute')->once()->with('manager', 'foo')->andReturnSelf();
        $parent->shouldReceive('save')->once()->andReturnTrue();

        $this->assertEquals($related, $relation->attach($related));
    }

    public function test_detach()
    {
        $relation = $this->getRelation();

        $parent = $relation->getParent();
        $parent->shouldReceive('setAttribute')->once()->with('manager', null)->andReturnSelf();
        $parent->shouldReceive('save')->once()->andReturnTrue();

        $this->assertTrue($relation->detach());
    }

    protected function getRelation()
    {
        $mockBuilder = m::mock(Builder::class);
        $mockBuilder->shouldReceive('clearFilters')->once()->withNoArgs()->andReturnSelf();
        $mockBuilder->shouldReceive('withoutGlobalScopes')->once()->withNoArgs()->andReturnSelf();
        $mockBuilder->shouldReceive('setModel')->once()->with(Entry::class)->andReturnSelf();

        $parent = m::mock(ModelHasOneStub::class);
        $parent->shouldReceive('getConnectionName')->andReturn('default');

        return new HasOne($mockBuilder, $parent, Entry::class, 'manager', 'dn');
    }
}

class ModelHasOneStub extends Model
{
    //
}
